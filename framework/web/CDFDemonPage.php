<?php
	/**
	 * Renders an HTML document to the requesting client (browser).
	 * <code>
	 *	$page = new CDFDemonPage();
	 *	$page->setPageTitle('Moo');
	 *	$page->render('main/home');
	 * </code>
	 *
	 * @author demon
	 */

	require_once 'import/savant/Savant3.php';
	require_once 'core/CDFExceptions.php';
	require_once 'core/CDFDataHelper.php';

	class CDFDemonPage
	{
		/**
		 * @var Savant3 Template renderer
		 */
		private $_template;
		/**
		 * @var string Filename of template to render
		 */
		private $_templatefile;
		private $_coretemplatefile;
		private $_attributes;

		/**
		 * @param string $templatepath Disk location of where templates are stored.
		 * @param string $coretemplate Template file that represents an entire HTML page.
		 */
		public function __construct($templatepath, $coretemplate = null)
		{
			$this->_attributes = array();
			$this->_template = new Savant3(array(
				'template_path' => $templatepath,
				'exceptions' => true
			));
			$this->_template->assignRef('Page', $this);
			$this->_coretemplatefile = $coretemplate;
		}

		//
		// Attributes
		// These are general purpose settings that can be queried by the template render,
		// but are local to this instance of a Page.
		//

		/**
		 * Sets an attribute for this page.
		 * @param string $key Key to index attribute.
		 * @param mixed $value Value of the attribute.
		 */
		protected function setAttribute($key, $value)
		{
			if(is_string($key) == false)
				throw new Exception('Invalid key for Page attribute');
			$this->_attributes[$key] = $value;
		}

		/**
		 * Retrieves a named attribute defined for this page.
		 * @param string $key Attribute index key.
		 * @return mixed The value of the attribute, or a string containing the name if not found.
		 */
		public function getAttribute($key)
		{
			if(array_key_exists($key, $this->_attributes) == false)
				return CDFDataHelper::AsStringSafe($key);

			return $this->_attributes[$key];
		}

		/**
		 * Defines the page title for the HTML document.  This would be queried by the template.
		 * @param string $title Title to use.
		 */
		public function setPageTitle($title)
		{
			if(is_string($title) == false)
				throw new CDFInvalidArgumentException('Page title must be a string');

			$this->setAttribute('PageTitle', CDFDataHelper::AsStringSafe($title));
		}

		//
		// Rendering
		//

		/**
		 * Renders the complete HTML document to the client.<br/>
		 * @param string $templatename Name of the template that defines the content for the HTML document (no extensions).
		 */
		public function render($templatename)
		{
			if($this->_coretemplatefile !== null)
			{
				$this->_templatefile = $templatename . '.tpl.php';
				ob_start();
				$this->_template->display($this->_coretemplatefile);
				ob_end_flush();
			}
			else
			{
				// no core template, treat $templatename as a whole page. calls to renderContent() will fail.
				ob_start();
				$this->_template->display($templatename . '.tpl.php');
				ob_end_flush();
			}
		}

		/**
		 * [INTERNAL] Allows the container template (core template) to render its content.
		 * This function should not be called unless it is in a core template (e.g. menu.tpl.php).
		 */
		public function renderContent()
		{
			if($this->_coretemplatefile === null)
				throw new CDFInvalidOperationException('renderContent cannot be called if page is standalone');
			$this->_template->display($this->_templatefile);
		}

		/**
		 * Defines a value for use in the page template.
		 * @param string $varkey Key that the page template will recognise.
		 * @param mixed $varvalue Any object, string, etc. for the template to use.
		 */
		public function setTemplateVariable($varkey, $varvalue)
		{
			if(!is_string($varkey))
				throw new CDFInvalidArgumentException('Template key must be string');
			$this->_template->assign($varkey, $varvalue);
		}

		//
		// HTTP utility
		//

		/**
		 * Tests if the current request is over HTTPS.
		 * @return bool True if the remote request is via HTTPS
		 */
		public function isHttpSecure()
		{
			return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off';
		}

		/**
		 * Redirects the client via HTTP to a relative (or absolute) URL.
		 * Script execution will end if calling this.
		 * @param string $url Relative or absolute URL to redirect.
		 */
		public function redirect($url)
		{
			$url = CDFDataHelper::AsStringSafe($url);
			if(strlen($url) == 0)
				throw new CDFInvalidArgumentException('URI in redirect is not specified');
			if(headers_sent())
				throw new CDFInvalidOperationException('Cannot redirect at this time');

			if($url[0] == '/')
				// uri is relative to the site, make it absolute
				$url = sprintf('%s://%s%s', $this->isHttpSecure() ? 'https' : 'http', $_SERVER['HTTP_HOST'], $url);

			header('Location: ' . $url);
			exit; // don't do anything else!
		}

		/**
		 * Returns the current URI/URL for the request, optionally stripping the query string.
		 * @param bool $keepquery Set to true to preserve the query string, false to remove it.
		 * @return string
		 */
		public static function getCurrentUri($keepquery = true)
		{
			$url = $_SERVER['REQUEST_URI'];
			if($keepquery == false)
			{
				$pos = strpos($url, '?');
				if($pos !== false)
				{
					if($pos > 0)
						$url = substr($url, 0, $pos);
					else
						$url = '/'; // if url is "?foo=bar" (with no actual path) then redirect to the root
				}
			}

			return $url;
		}

		/**
		 * Causes the client to redirect to the same page.
		 * Script execution will end if calling this.
		 * @param bool $keepquery Set to true to preserve the query string, false to remove it.
		 */
		public function reload($keepquery = true)
		{
			$this->redirect($this->getCurrentUri($keepquery));
		}

		//
		// Post back
		//

		/**
		 * Tests if the current request has HTTP POST data, optionally originating from a named "submit" button.
		 * @param string|null $submitname Name of the <input type="submit"> form field or null if don't care.
		 * @return bool True if post back detected.
		 */
		public function isPostBack($submitname = null)
		{
			// if no submit button name passed in, just check if there was any post variables
			if($submitname == null)
				return count($_POST) > 0;

			// check if the specified form field is present in the postback
			return isset($_POST[$submitname]);
		}

		/**
		 * Gets the specified variable from the POST array.
		 * @param string $name Key for the POST field
		 * @param bool $unsafe If true, will not pass it to safe string parsing. Use with absolute caution.
		 * @return string|null Value of the field or null if not found
		 */
		public function getPostVariable($name, $unsafe = false)
		{
			if(isset($_POST[$name]))
				return $unsafe === true ? $_POST[$name] : CDFDataHelper::AsStringSafe($_POST[$name], true);

			return null;
		}

		/**
		 * Returns true if the specified variable is in the postback.
		 * @param string $name Key for the POST field.
		 * @return bool True if the variable was found.
		 */
		public function hasPostVariable($name)
		{
			return isset($_POST[$name]) ? true : false;
		}

	}
