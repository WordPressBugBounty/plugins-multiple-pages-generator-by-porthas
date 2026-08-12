let hasChanges = false;

export function markUnsavedChanges() {
	hasChanges = true;
}

export function clearUnsavedChanges() {
	hasChanges = false;
}

export function hasUnsavedChanges() {
	return hasChanges;
}

/**
 * Persist both halves of the main project form as one user-visible save operation.
 * Dirty state is cleared only after both requests report success.
 *
 * @param {Function} saveMain Persist the main project fields and return the project ID.
 * @param {Function} saveUrl  Persist the URL fields and return true on success.
 * @return {Promise<{success: boolean, projectId: number|boolean, error?: Error}>} Save result.
 */
export async function persistProjectChanges( saveMain, saveUrl ) {
	try {
		const projectId = await saveMain();
		if ( ! projectId ) {
			return { success: false, projectId: false };
		}

		const urlSaved = await saveUrl( projectId );
		if ( urlSaved !== true ) {
			return { success: false, projectId };
		}

		clearUnsavedChanges();
		return { success: true, projectId };
	} catch ( error ) {
		return { success: false, projectId: false, error };
	}
}
