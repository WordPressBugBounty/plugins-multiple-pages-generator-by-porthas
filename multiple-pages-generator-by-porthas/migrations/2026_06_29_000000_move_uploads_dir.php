<?php
/**
 * Migration: move stored files from wp-content/mpg-uploads/ to the uploads/mpg/ directory.
 *
 * Backup, staging and migration tools that operate on the standard wp-content/uploads/
 * directory did not see files stored in the old custom location. This migration moves
 * the legacy tree so those tools handle MPG files automatically (issue #686).
 *
 * @package MultiPagesGenerator
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return new class extends \ThemeisleSDK\Modules\Abstract_Migration {
	/**
	 * Source entries intentionally retained because a different destination already exists.
	 *
	 * These are safe, terminal conflicts: MPG prefers the current uploads tree, while the legacy
	 * copy remains untouched for recovery. Recording them lets the SDK complete without retrying
	 * filesystem scans on every request.
	 *
	 * @var array<string,bool>
	 */
	private $retained_conflicts = array();

	/**
	 * Only run when the legacy directory still exists on disk.
	 *
	 * @return bool
	 */
	public function should_run() {
		return is_dir( untrailingslashit( MPG_LEGACY_UPLOADS_DIR ) );
	}

	/**
	 * Move files from wp-content/mpg-uploads/ to the uploads/mpg/ directory.
	 *
	 * Uses a single atomic rename when the target does not yet exist, falling back
	 * to a deep recursive merge that never overwrites files already in the target
	 * location. Files that cannot be renamed (e.g. the uploads dir lives on a
	 * different filesystem, where rename() always fails) are copied via
	 * WP_Filesystem and the source removed afterwards.
	 *
	 * @return void
	 */
	public function up() {
		$legacy = untrailingslashit( MPG_LEGACY_UPLOADS_DIR );
		$target = untrailingslashit( MPG_UPLOADS_DIR );

		// Fast path: atomic rename when the new location does not yet exist.
		if ( ! file_exists( $target ) && ! is_link( $target ) ) {
			wp_mkdir_p( dirname( $target ) );
			if ( @rename( $legacy, $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return;
			}
		}

		// Slow path: recursively merge the two trees, never overwriting existing target files.
		// Do not follow a symlinked root when a direct rename was not possible.
		if ( is_link( $legacy ) ) {
			return;
		}
		$complete = $this->merge_dirs( $legacy, $target );

		if ( ! $complete || ( is_dir( $legacy ) && ! $this->has_retained_conflict_under( $legacy ) ) ) {
			// Genuine operational failures remain retryable. Intentional destination conflicts are
			// preserved silently and do not keep the SDK migration pending on every request.
			throw new RuntimeException(
				sprintf( 'Could not move every MPG file from %s to %s.', $legacy, $target )
			);
		}
	}

	/**
	 * Recursively merge $src into $dst, never overwriting existing destination files.
	 * Source directories are removed once all their movable children have been processed.
	 *
	 * @param string $src Source directory path (no trailing slash).
	 * @param string $dst Destination directory path (no trailing slash).
	 * @return bool Whether every source entry was migrated and the source directory was removed.
	 */
	private function merge_dirs( $src, $dst ) {
		if ( is_link( $src ) ) {
			return false;
		}
		if ( ! is_dir( $src ) ) {
			return true;
		}
		if ( is_link( $dst ) || ( file_exists( $dst ) && ! is_dir( $dst ) ) ) {
			$this->retained_conflicts[ $src ] = true;
			return true;
		}
		wp_mkdir_p( $dst );
		$entries = @scandir( $src ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $entries ) ) {
			return false;
		}

		$complete = true;
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$src_entry = $src . DIRECTORY_SEPARATOR . $entry;
			$dst_entry = $dst . DIRECTORY_SEPARATOR . $entry;
			if ( is_link( $src_entry ) ) {
				// Preserve links only when the link itself can be renamed. Never copy a link through
				// WP_Filesystem, because copy implementations may dereference directory links and move
				// data that is outside MPG's storage tree.
				if ( file_exists( $dst_entry ) || is_link( $dst_entry ) ) {
					$this->retained_conflicts[ $src_entry ] = true;
				} elseif ( ! @rename( $src_entry, $dst_entry ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					$complete = false;
				}
			} elseif ( is_dir( $src_entry ) ) {
				$complete = $this->merge_dirs( $src_entry, $dst_entry ) && $complete;
			} elseif ( file_exists( $dst_entry ) || is_link( $dst_entry ) ) {
				// A prior partial attempt may have copied the file but failed before deleting the source.
				// Identical duplicates are safe to remove. A different destination is never overwritten;
				// retain the legacy copy and report an incomplete migration so it can be inspected/retried.
				$src_hash = hash_file( 'sha256', $src_entry );
				$dst_hash = is_file( $dst_entry ) ? hash_file( 'sha256', $dst_entry ) : false;
				if ( is_string( $src_hash ) && is_string( $dst_hash ) && hash_equals( $src_hash, $dst_hash ) ) {
					$complete = @unlink( $src_entry ) && $complete; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				} else {
					$this->retained_conflicts[ $src_entry ] = true;
				}
			} else {
				$complete = $this->move_file( $src_entry, $dst_entry ) && $complete;
			}
		}

		if ( ! @rmdir( $src ) && is_dir( $src ) && ! $this->has_retained_conflict_under( $src ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$complete = false;
		}

		return $complete;
	}

	/**
	 * Check whether a directory remains only because it contains a deliberately preserved conflict.
	 *
	 * @param string $path Absolute source path.
	 * @return bool Whether a retained conflict is at or below this path.
	 */
	private function has_retained_conflict_under( $path ) {
		$path_prefix = trailingslashit( $path );
		foreach ( array_keys( $this->retained_conflicts ) as $conflict_path ) {
			if ( $conflict_path === $path || 0 === strpos( $conflict_path, $path_prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Move a single file, falling back to a WP_Filesystem copy + delete when rename() fails
	 * (rename cannot cross filesystem boundaries, e.g. uploads on a separate mount/symlink).
	 *
	 * @param string $src Absolute source file path.
	 * @param string $dst Absolute destination file path.
	 * @return bool Whether the file ended up at the destination.
	 */
	private function move_file( $src, $dst ) {
		if ( @rename( $src, $dst ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return true;
		}

		$filesystem = $this->filesystem();
		if ( $filesystem && $filesystem->copy( $src, $dst, false, defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : false ) ) {
			$filesystem->delete( $src );
			return true;
		}

		return false;
	}

	/**
	 * Get the initialized WP_Filesystem instance, or null when it cannot be set up
	 * without credentials (the migration then leaves the files in place).
	 *
	 * @return \WP_Filesystem_Base|null
	 */
	private function filesystem() {
		global $wp_filesystem;

		if ( ! $wp_filesystem instanceof \WP_Filesystem_Base ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		return $wp_filesystem instanceof \WP_Filesystem_Base ? $wp_filesystem : null;
	}
};
