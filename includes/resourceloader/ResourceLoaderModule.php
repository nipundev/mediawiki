<?php
/**
 * Abstraction for resource loader modules.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @author Trevor Parscal
 * @author Roan Kattouw
 */

/**
 * Abstraction for resource loader modules, with name registration and maxage functionality.
 */
abstract class ResourceLoaderModule {

	# Type of resource
	const TYPE_SCRIPTS = 'scripts';
	const TYPE_STYLES = 'styles';
	const TYPE_MESSAGES = 'messages';
	const TYPE_COMBINED = 'combined';

	# sitewide core module like a skin file or jQuery component
	const ORIGIN_CORE_SITEWIDE = 1;

	# per-user module generated by the software
	const ORIGIN_CORE_INDIVIDUAL = 2;

	# sitewide module generated from user-editable files, like MediaWiki:Common.js, or
	# modules accessible to multiple users, such as those generated by the Gadgets extension.
	const ORIGIN_USER_SITEWIDE = 3;

	# per-user module generated from user-editable files, like User:Me/vector.js
	const ORIGIN_USER_INDIVIDUAL = 4;

	# an access constant; make sure this is kept as the largest number in this group
	const ORIGIN_ALL = 10;

	# script and style modules form a hierarchy of trustworthiness, with core modules like
	# skins and jQuery as most trustworthy, and user scripts as least trustworthy.  We can
	# limit the types of scripts and styles we allow to load on, say, sensitive special
	# pages like Special:UserLogin and Special:Preferences
	protected $origin = self::ORIGIN_CORE_SITEWIDE;

	/* Protected Members */

	protected $name = null;
	protected $targets = array( 'desktop' );

	// In-object cache for file dependencies
	protected $fileDeps = array();
	// In-object cache for message blob mtime
	protected $msgBlobMtime = array();

	/* Methods */

	/**
	 * Get this module's name. This is set when the module is registered
	 * with ResourceLoader::register()
	 *
	 * @return Mixed: Name (string) or null if no name was set
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Set this module's name. This is called by ResourceLoader::register()
	 * when registering the module. Other code should not call this.
	 *
	 * @param string $name Name
	 */
	public function setName( $name ) {
		$this->name = $name;
	}

	/**
	 * Get this module's origin. This is set when the module is registered
	 * with ResourceLoader::register()
	 *
	 * @return Int ResourceLoaderModule class constant, the subclass default
	 *     if not set manually
	 */
	public function getOrigin() {
		return $this->origin;
	}

	/**
	 * Set this module's origin. This is called by ResourceLodaer::register()
	 * when registering the module. Other code should not call this.
	 *
	 * @param int $origin origin
	 */
	public function setOrigin( $origin ) {
		$this->origin = $origin;
	}

	/**
	 * @param $context ResourceLoaderContext
	 * @return bool
	 */
	public function getFlip( $context ) {
		global $wgContLang;

		return $wgContLang->getDir() !== $context->getDirection();
	}

	/**
	 * Get all JS for this module for a given language and skin.
	 * Includes all relevant JS except loader scripts.
	 *
	 * @param $context ResourceLoaderContext: Context object
	 * @return String: JavaScript code
	 */
	public function getScript( ResourceLoaderContext $context ) {
		// Stub, override expected
		return '';
	}

	/**
	 * Get the URL or URLs to load for this module's JS in debug mode.
	 * The default behavior is to return a load.php?only=scripts URL for
	 * the module, but file-based modules will want to override this to
	 * load the files directly.
	 *
	 * This function is called only when 1) we're in debug mode, 2) there
	 * is no only= parameter and 3) supportsURLLoading() returns true.
	 * #2 is important to prevent an infinite loop, therefore this function
	 * MUST return either an only= URL or a non-load.php URL.
	 *
	 * @param $context ResourceLoaderContext: Context object
	 * @return Array of URLs
	 */
	public function getScriptURLsForDebug( ResourceLoaderContext $context ) {
		$url = ResourceLoader::makeLoaderURL(
			array( $this->getName() ),
			$context->getLanguage(),
			$context->getSkin(),
			$context->getUser(),
			$context->getVersion(),
			true, // debug
			'scripts', // only
			$context->getRequest()->getBool( 'printable' ),
			$context->getRequest()->getBool( 'handheld' )
		);
		return array( $url );
	}

	/**
	 * Whether this module supports URL loading. If this function returns false,
	 * getScript() will be used even in cases (debug mode, no only param) where
	 * getScriptURLsForDebug() would normally be used instead.
	 * @return bool
	 */
	public function supportsURLLoading() {
		return true;
	}

	/**
	 * Get all CSS for this module for a given skin.
	 *
	 * @param $context ResourceLoaderContext: Context object
	 * @return Array: List of CSS strings or array of CSS strings keyed by media type.
	 *  like array( 'screen' => '.foo { width: 0 }' );
	 *  or array( 'screen' => array( '.foo { width: 0 }' ) );
	 */
	public function getStyles( ResourceLoaderContext $context ) {
		// Stub, override expected
		return array();
	}

	/**
	 * Get the URL or URLs to load for this module's CSS in debug mode.
	 * The default behavior is to return a load.php?only=styles URL for
	 * the module, but file-based modules will want to override this to
	 * load the files directly. See also getScriptURLsForDebug()
	 *
	 * @param $context ResourceLoaderContext: Context object
	 * @return Array: array( mediaType => array( URL1, URL2, ... ), ... )
	 */
	public function getStyleURLsForDebug( ResourceLoaderContext $context ) {
		$url = ResourceLoader::makeLoaderURL(
			array( $this->getName() ),
			$context->getLanguage(),
			$context->getSkin(),
			$context->getUser(),
			$context->getVersion(),
			true, // debug
			'styles', // only
			$context->getRequest()->getBool( 'printable' ),
			$context->getRequest()->getBool( 'handheld' )
		);
		return array( 'all' => array( $url ) );
	}

	/**
	 * Get the messages needed for this module.
	 *
	 * To get a JSON blob with messages, use MessageBlobStore::get()
	 *
	 * @return Array: List of message keys. Keys may occur more than once
	 */
	public function getMessages() {
		// Stub, override expected
		return array();
	}

	/**
	 * Get the group this module is in.
	 *
	 * @return String: Group name
	 */
	public function getGroup() {
		// Stub, override expected
		return null;
	}

	/**
	 * Get the origin of this module. Should only be overridden for foreign modules.
	 *
	 * @return String: Origin name, 'local' for local modules
	 */
	public function getSource() {
		// Stub, override expected
		return 'local';
	}

	/**
	 * Where on the HTML page should this module's JS be loaded?
	 *  - 'top': in the "<head>"
	 *  - 'bottom': at the bottom of the "<body>"
	 *
	 * @return string
	 */
	public function getPosition() {
		return 'bottom';
	}

	/**
	 * Whether this module's JS expects to work without the client-side ResourceLoader module.
	 * Returning true from this function will prevent mw.loader.state() call from being
	 * appended to the bottom of the script.
	 *
	 * @return bool
	 */
	public function isRaw() {
		return false;
	}

	/**
	 * Get the loader JS for this module, if set.
	 *
	 * @return Mixed: JavaScript loader code as a string or boolean false if no custom loader set
	 */
	public function getLoaderScript() {
		// Stub, override expected
		return false;
	}

	/**
	 * Get a list of modules this module depends on.
	 *
	 * Dependency information is taken into account when loading a module
	 * on the client side. When adding a module on the server side,
	 * dependency information is NOT taken into account and YOU are
	 * responsible for adding dependent modules as well. If you don't do
	 * this, the client side loader will send a second request back to the
	 * server to fetch the missing modules, which kind of defeats the
	 * purpose of the resource loader.
	 *
	 * To add dependencies dynamically on the client side, use a custom
	 * loader script, see getLoaderScript()
	 * @return Array: List of module names as strings
	 */
	public function getDependencies() {
		// Stub, override expected
		return array();
	}

	/**
	 * Get target(s) for the module, eg ['desktop'] or ['desktop', 'mobile']
	 *
	 * @return array of strings
	 */
	public function getTargets() {
		return $this->targets;
	}

	/**
	 * Get the files this module depends on indirectly for a given skin.
	 * Currently these are only image files referenced by the module's CSS.
	 *
	 * @param string $skin Skin name
	 * @return Array: List of files
	 */
	public function getFileDependencies( $skin ) {
		// Try in-object cache first
		if ( isset( $this->fileDeps[$skin] ) ) {
			return $this->fileDeps[$skin];
		}

		$dbr = wfGetDB( DB_SLAVE );
		$deps = $dbr->selectField( 'module_deps', 'md_deps', array(
				'md_module' => $this->getName(),
				'md_skin' => $skin,
			), __METHOD__
		);
		if ( !is_null( $deps ) ) {
			$this->fileDeps[$skin] = (array) FormatJson::decode( $deps, true );
		} else {
			$this->fileDeps[$skin] = array();
		}
		return $this->fileDeps[$skin];
	}

	/**
	 * Set preloaded file dependency information. Used so we can load this
	 * information for all modules at once.
	 * @param string $skin Skin name
	 * @param array $deps Array of file names
	 */
	public function setFileDependencies( $skin, $deps ) {
		$this->fileDeps[$skin] = $deps;
	}

	/**
	 * Get the last modification timestamp of the message blob for this
	 * module in a given language.
	 * @param string $lang Language code
	 * @return Integer: UNIX timestamp, or 0 if the module doesn't have messages
	 */
	public function getMsgBlobMtime( $lang ) {
		if ( !isset( $this->msgBlobMtime[$lang] ) ) {
			if ( !count( $this->getMessages() ) ) {
				return 0;
			}

			$dbr = wfGetDB( DB_SLAVE );
			$msgBlobMtime = $dbr->selectField( 'msg_resource', 'mr_timestamp', array(
					'mr_resource' => $this->getName(),
					'mr_lang' => $lang
				), __METHOD__
			);
			// If no blob was found, but the module does have messages, that means we need
			// to regenerate it. Return NOW
			if ( $msgBlobMtime === false ) {
				$msgBlobMtime = wfTimestampNow();
			}
			$this->msgBlobMtime[$lang] = wfTimestamp( TS_UNIX, $msgBlobMtime );
		}
		return $this->msgBlobMtime[$lang];
	}

	/**
	 * Set a preloaded message blob last modification timestamp. Used so we
	 * can load this information for all modules at once.
	 * @param string $lang Language code
	 * @param $mtime Integer: UNIX timestamp or 0 if there is no such blob
	 */
	public function setMsgBlobMtime( $lang, $mtime ) {
		$this->msgBlobMtime[$lang] = $mtime;
	}

	/* Abstract Methods */

	/**
	 * Get this module's last modification timestamp for a given
	 * combination of language, skin and debug mode flag. This is typically
	 * the highest of each of the relevant components' modification
	 * timestamps. Whenever anything happens that changes the module's
	 * contents for these parameters, the mtime should increase.
	 *
	 * NOTE: The mtime of the module's messages is NOT automatically included.
	 * If you want this to happen, you'll need to call getMsgBlobMtime()
	 * yourself and take its result into consideration.
	 *
	 * @param $context ResourceLoaderContext: Context object
	 * @return Integer: UNIX timestamp
	 */
	public function getModifiedTime( ResourceLoaderContext $context ) {
		// 0 would mean now
		return 1;
	}

	/**
	 * Check whether this module is known to be empty. If a child class
	 * has an easy and cheap way to determine that this module is
	 * definitely going to be empty, it should override this method to
	 * return true in that case. Callers may optimize the request for this
	 * module away if this function returns true.
	 * @param $context ResourceLoaderContext: Context object
	 * @return Boolean
	 */
	public function isKnownEmpty( ResourceLoaderContext $context ) {
		return false;
	}

	/** @var JSParser lazy-initialized; use self::javaScriptParser() */
	private static $jsParser;
	private static $parseCacheVersion = 1;

	/**
	 * Validate a given script file; if valid returns the original source.
	 * If invalid, returns replacement JS source that throws an exception.
	 *
	 * @param string $fileName
	 * @param string $contents
	 * @return string JS with the original, or a replacement error
	 */
	protected function validateScriptFile( $fileName, $contents ) {
		global $wgResourceLoaderValidateJS;
		if ( $wgResourceLoaderValidateJS ) {
			// Try for cache hit
			// Use CACHE_ANYTHING since filtering is very slow compared to DB queries
			$key = wfMemcKey( 'resourceloader', 'jsparse', self::$parseCacheVersion, md5( $contents ) );
			$cache = wfGetCache( CACHE_ANYTHING );
			$cacheEntry = $cache->get( $key );
			if ( is_string( $cacheEntry ) ) {
				return $cacheEntry;
			}

			$parser = self::javaScriptParser();
			try {
				$parser->parse( $contents, $fileName, 1 );
				$result = $contents;
			} catch ( Exception $e ) {
				// We'll save this to cache to avoid having to validate broken JS over and over...
				$err = $e->getMessage();
				$result = "throw new Error(" . Xml::encodeJsVar( "JavaScript parse error: $err" ) . ");";
			}

			$cache->set( $key, $result );
			return $result;
		} else {
			return $contents;
		}
	}

	/**
	 * @return JSParser
	 */
	protected static function javaScriptParser() {
		if ( !self::$jsParser ) {
			self::$jsParser = new JSParser();
		}
		return self::$jsParser;
	}

	/**
	 * Safe version of filemtime(), which doesn't throw a PHP warning if the file doesn't exist
	 * but returns 1 instead.
	 * @param string $filename File name
	 * @return int UNIX timestamp, or 1 if the file doesn't exist
	 */
	protected static function safeFilemtime( $filename ) {
		if ( file_exists( $filename ) ) {
			return filemtime( $filename );
		} else {
			// We only ever map this function on an array if we're gonna call max() after,
			// so return our standard minimum timestamps here. This is 1, not 0, because
			// wfTimestamp(0) == NOW
			return 1;
		}
	}
}
