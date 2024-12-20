<?php

/**
 * @category   class
 * @package    SitemapEngine
 * @license    http://www.gnu.org/licenses/gpl.html  GPL V 2.0
 * @version    2.0
 * @see        http://www.sitemaps.org/protocol.php
 * @see        http://en.wikipedia.org/wiki/Sitemaps
 * @see        http://en.wikipedia.org/wiki/Sitemap_index
 */

class MPG_SitemapGenerator
{
    /**
     * Name of sitemap file
     * @var string
     * @access public
     */
    public $sitemapFileName = "multipage-sitemap.xml";
    /**
     * Name of sitemap index file
     * @var string
     * @access public
     */

    public $sitemapIndexFileName = "multipage-sitemap-index.xml";
    /**
     * Robots file name
     * @var string
     * @access public
     */
    public $robotsFileName = "robots.txt";
    /**
     * Quantity of URLs per single sitemap file.
     * According to specification max value is 50.000.
     * If Your links are very long, sitemap file can be bigger than 10MB,
     * in this case use smaller value.
     * @var int
     * @access public
     */
    public $maxURLsPerSitemap = 50000;
    /**
     * If true, two sitemap files (.xml and .xml.gz) will be created and added to robots.txt.
     * If true, .gz file will be submitted to search engines.
     * If quantity of URLs will be bigger than 50.000, option will be ignored,
     * all sitemap files except sitemap index will be compressed.
     * @var bool
     * @access public
     */
    public $createGZipFile = false;
    /**
     * URL to Your site.
     * Script will use it to send sitemaps to search engines.
     * @var string
     * @access private
     */
    private $baseURL;
    /**
     * Base path. Relative to script location.
     * Use this if Your sitemap and robots files should be stored in other
     * directory then script.
     * @var string
     * @access private
     */
    private $basePath;
    /**
     * Version of this class
     * @var string
     * @access private
     */
    private $classVersion = "1.2.0";
    /**
     * Search engines URLs
     * @var array of strings
     * @access private
     */
    /**
     * Array with urls
     * @var array of strings
     * @access private
     */
    private $urls;
    /**
     * Array with sitemap
     * @var array of strings
     * @access private
     */

    private $sitemaps;
    /**
     * Array with sitemap index
     * @var array of strings
     * @access private
     */

    private $sitemapIndex;
    /**
     * Current sitemap full URL
     * @var string
     * @access private
     */
    private $sitemapFullURL;

    /**
     * Constructor.
     * @param string $baseURL You site URL, with / at the end.
     * @param string|null $basePath Relative path where sitemap and robots should be stored.
     */
    public function __construct($baseURL, $basePath = "")
    {
        $this->baseURL = $baseURL;
        $this->basePath = $basePath;
    }

    /**
     * @param int $maxURLsPerSitemap
     */
    public function setMaxURLsPerSitemap($maxURLsPerSitemap)
    {
        $this->maxURLsPerSitemap = $maxURLsPerSitemap;
    }

    /**
     * @param string $sitemapFileName
     */
    public function setSitemapFileName($sitemapFileName)
    {
        $this->sitemapFileName = $sitemapFileName . ".xml";
        $this->sitemapIndexFileName = $sitemapFileName . "-index.xml";
    }

    /**
     * Use this to add single URL to sitemap.
     * @param string $url URL
     * @param string $lastModified When it was modified, use ISO 8601
     * @param string $changeFrequency How often search engines should revisit this URL
     * @param string $priority Priority of URL on You site
     * @see http://en.wikipedia.org/wiki/ISO_8601
     * @see http://php.net/manual/en/function.date.php
     */
	public function addUrl( $url, $lastModified = null, $changeFrequency = null, $priority = null ) {
		if ( empty( $url ) ) {
			throw new InvalidArgumentException( "URL is mandatory. At least one argument should be given." );
		}
		$urlLenght = extension_loaded( 'mbstring' ) ? mb_strlen( $url ) : strlen( $url );
		if ( $urlLenght > 2048 ) {
			throw new InvalidArgumentException( "URL lenght can't be bigger than 2048 characters.
                                                    Note, that precise url length check is guaranteed only using mb_string extension.
                                                    Make sure Your server allow to use mbstring extension." );
		}
		$tmp        = array();
		$tmp['loc'] = $url;
		if ( ! empty( $lastModified ) && strtotime( $lastModified ) !== false ) {
			$tmp['lastmod'] = $lastModified;
		}
		if ( ! empty( $changeFrequency ) ) {
			$tmp['changefreq'] = $changeFrequency;
		}
		if ( ! empty( $priority ) ) {
			$tmp['priority'] = $priority;
		}
		$this->urls[] = $tmp;
	}
    /**
     * Create sitemap in memory.
     */
    public function createSitemap()
    {
	    if ( empty( $this->urls ) ) {
		    throw new BadMethodCallException( "To create sitemap, call addUrl or addUrls function first." );
	    }
	    if ( $this->maxURLsPerSitemap > 50000 ) {
		    throw new InvalidArgumentException( "More than 50,000 URLs per single sitemap is not allowed." );
	    }

        $generatorInfo = '';
        $sitemapHeader = '<?xml version="1.0" encoding="UTF-8"?> <urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
        $sitemapIndexHeader = '<?xml version="1.0" encoding="UTF-8"?>' . $generatorInfo . '
                                    <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                                  </sitemapindex>';
        foreach (array_chunk($this->urls, $this->maxURLsPerSitemap) as $sitemap) {
            $xml = new SimpleXMLElement($sitemapHeader);

            foreach ($sitemap as $url) {

	            $row = $xml->addChild( 'url' );
	            $row->addChild( 'loc', htmlspecialchars( $url['loc'], ENT_QUOTES, 'UTF-8' ) );
	            if ( isset( $url['lastmod'] ) ) {
		            $row->addChild( 'lastmod', $url['lastmod'] );
	            }
	            if ( isset( $url['changefreq'] ) ) {
		            $row->addChild( 'changefreq', $url['changefreq'] );
	            }
	            if ( isset( $url['priority'] ) ) {
		            $row->addChild( 'priority', $url['priority'] );
	            }
            }

            if (strlen($xml->asXML()) > 10485760) {
                throw new LengthException("Sitemap size is more than 10MB (10,485,760), please decrease maxURLsPerSitemap variable.");
            }
            $this->sitemaps[] = $xml->asXML();
        }
        if (count($this->sitemaps) > 1000)
            throw new LengthException("Sitemap index can contains 1000 single sitemaps. Perhaps You trying to submit too many URLs.");
        if (count($this->sitemaps) > 1) {
            for ($i = 0; $i < count($this->sitemaps); $i++) {
                $this->sitemaps[$i] = array(
                    str_replace(".xml", ($i + 1) . ".xml", $this->sitemapFileName), //.xml.gz
                    $this->sitemaps[$i]
                );
            }
            $xml = new SimpleXMLElement($sitemapIndexHeader);
            foreach ($this->sitemaps as $sitemap) {
                $row = $xml->addChild('sitemap');
                $row->addChild('loc', $this->baseURL . htmlentities($sitemap[0]));
                $row->addChild('lastmod', date('c'));
            }
            $this->sitemapFullURL = $this->baseURL . $this->sitemapIndexFileName;
            $this->sitemapIndex = array(
                $this->sitemapIndexFileName,
                $xml->asXML()
            );
        } else {
            if ($this->createGZipFile) {
                $this->sitemapFullURL = $this->baseURL . $this->sitemapFileName . ".gz";
            } else {
                $this->sitemapFullURL = $this->baseURL . $this->sitemapFileName;
                $this->sitemaps[0] = array(
                    $this->sitemapFileName,
                    $this->sitemaps[0]
                );
            }
        }
    }
    /**
     * Returns created sitemaps as array of strings.
     * Use it You want to work with sitemap without saving it as files.
     * @return array of strings
     * @access public
     */
    public function toArray()
    {
        if (isset($this->sitemapIndex))
            return array_merge(array($this->sitemapIndex), $this->sitemaps);
        else
            return $this->sitemaps;
    }
    /**
     * Will write sitemaps as files.
     * @access public
     */
    public function writeSitemap()
    {
        try {

            if (empty($this->sitemaps))
                throw new BadMethodCallException("To write sitemap, call createSitemap function first.");
            if (!empty($this->sitemapIndex)) {
                $this->_writeFile($this->sitemapIndex[1], $this->basePath, $this->sitemapIndex[0]);
                foreach ($this->sitemaps as $sitemap) {
                    if ($this->createGZipFile)
                        $this->_writeGZipFile($sitemap[1], $this->basePath, $sitemap[0]);
                    else
                        $this->_writeFile($sitemap[1], $this->basePath, $sitemap[0]);
                }
            } else {
                $this->_writeFile($this->sitemaps[0][1], $this->basePath, $this->sitemaps[0][0]);
                if ($this->createGZipFile)
                    $this->_writeGZipFile($this->sitemaps[0][1], $this->basePath, $this->sitemaps[0][0] . ".gz");
            }
        } catch (Exception $e) {
            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );
            echo $e->getMessage();
        }
    }
    /**
     * If robots.txt file exist, will update information about newly created sitemaps.
     * If there is no robots.txt will, create one and put into it information about sitemaps.
     * @access public
     */
    public function updateRobots()
    {
        if (empty($this->sitemaps))
            throw new BadMethodCallException("To update robots.txt, call createSitemap function first.");
        $sampleRobotsFile = "User-agent: *\nAllow: /";
        if (file_exists($this->basePath . $this->robotsFileName)) {
            $robotsFile = explode("\n", file_get_contents($this->basePath . $this->robotsFileName));
            $robotsFileContent = "";
            foreach ($robotsFile as $key => $value) {
                //                if(substr($value, 0, 8) == 'Sitemap:') unset($robotsFile[$key]);
                if (strpos($value, $this->sitemapFileName) !== false or strpos($value, $this->sitemapIndexFileName) !== false) unset($robotsFile[$key]);
                else $robotsFileContent .= $value . "\n";
            }
            $robotsFileContent .= "Sitemap: $this->sitemapFullURL";
            if ($this->createGZipFile && !isset($this->sitemapIndex))
                $robotsFileContent .= "\nSitemap: " . $this->sitemapFullURL . ".gz";
            file_put_contents($this->basePath . $this->robotsFileName, $robotsFileContent);
        } else {
            $sampleRobotsFile = $sampleRobotsFile . "\n\nSitemap: " . $this->sitemapFullURL;
            if ($this->createGZipFile && !isset($this->sitemapIndex))
                $sampleRobotsFile .= "\nSitemap: " . $this->sitemapFullURL . ".gz";
            file_put_contents($this->basePath . $this->robotsFileName, $sampleRobotsFile);
        }
    }

    /**
     * Save file.
     * @param string $content
     * @param string $filePath
     * @param string $fileName
     * @return bool
     * @access private
     */
    private function _writeFile($content, $filePath, $fileName)
    {
	    if ( empty( $content ) ) {
		    return false;
	    }
	    global $wp_filesystem;
	    require_once ABSPATH . 'wp-admin/includes/file.php';
	    WP_Filesystem();

	    $wp_filesystem->put_contents(
		    $filePath . $fileName,
		    $content,
		    FS_CHMOD_FILE // predefined file permissions constant
	    );
    }
    /**
     * Save GZipped file.
     * @param string $content
     * @param string $filePath
     * @param string $fileName
     * @return bool
     * @access private
     */
    private function _writeGZipFile($content, $filePath, $fileName)
    {
	    if ( empty( $content ) ) {
		    return false;
	    }
        $file = gzopen($filePath . $fileName, 'w');
        gzwrite($file, $content);
        return gzclose($file);
    }
    /**
     * @return string
     */
    public function getSitemapFullURL()
    {
        return htmlspecialchars($this->sitemapFullURL, ENT_QUOTES, 'UTF-8');
    }

	public static function get_basepath() {
		if ( get_option( 'mpg_site_basepath' ) ) {
			$site_root_path = rtrim( get_option( 'mpg_site_basepath' )['value'], '/' ) . '/';
		} else {
			$site_root_path = get_home_path();

			if ( ! is_writable( $site_root_path ) && ! empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
				$site_root_path = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR;
			}
		}

		return $site_root_path;
	}

	/**
	 * Check if we should create sitemap for project.
	 *
	 * @param $project
	 * @param mixed $urls_array
	 */
	public static function maybe_create_sitemap( $project,$urls_array ) {
		$sitemap_filename = $project->sitemap_filename ?? '';

		if ( empty( $sitemap_filename ) ) {
			return;
		}
		$sitemap_max_url          = $project->sitemap_max_url ?: 5000;
		$sitemap_update_frequency = $project->sitemap_update_frequency ?: 'daily';
		$sitemap_add_to_robots    = $project->sitemap_add_to_robots ?: true;

		self::run( $urls_array, $sitemap_filename, $sitemap_max_url, $sitemap_update_frequency, $sitemap_add_to_robots, $project->id );

	}
    public static function run($urlsArray, $sitemap_name, $sitemap_max_url, $sitemap_update_freq, $add_to_robots, $project_id)
    {

        try {

            $project = MPG_ProjectModel::get_project_by_id($project_id);
            $site_root_path = self::get_basepath();
	        if ( empty( $project->template_id ) ) {
		        throw new Exception( __( 'Project has no template.', 'multiple-pages-generator-by-porthas' ) );
	        }
	        $frenquency = ! empty( $sitemap_update_freq ) ? $sitemap_update_freq : 'monthly';
	        $last_mod = get_the_modified_date( 'Y-m-d', $project->template_id );


            $siteMap = new MPG_SitemapGenerator( trailingslashit( get_site_url() ), $site_root_path );
	        foreach ( $urlsArray as $index_url => $url ) {
		        if ( $project->url_mode === 'without-trailing-slash' ) {
			        $url = rtrim( $url, '/' );
		        }
		        MPG_CoreModel::set_current_row( $project_id, $index_url );
		        $row_last_modified = MPG_ProjectModel::get_vpage_modified_date( $project_id );
		        if ( $row_last_modified !== false ) {
			        $last_mod = date( 'Y-m-d', $row_last_modified );
		        }
		        $siteMap->addUrl( MPG_CoreModel::path_to_url( $url ), $last_mod, $frenquency, $project->sitemap_priority );
	        }


            if ($sitemap_name) {
                $siteMap->setSitemapFileName($sitemap_name);
            }
            if ($sitemap_max_url) {
                $siteMap->setMaxURLsPerSitemap($sitemap_max_url);
            } // set max URLs

            $siteMap->createSitemap(); // create sitemap
            $siteMap->writeSitemap(); // write sitemap as file

            if ($add_to_robots) {
                $siteMap->updateRobots();
            } // update robots.txt file

            return $siteMap->getSitemapFullURL();
        } catch (Exception $e) {
            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );
            throw new Exception($e->getMessage());
        }
    }
}
