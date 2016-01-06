<?php

/**
 * Class CDFConsoleLaunch
 * Utility method to launch an external application as a background task.
 */
class CDFConsoleLaunch
{
	/**
	 * Launches a PHP application in the background.
	 * Note that on Unix, the application will be running as the same user that called the method.
	 * @param string $pathPhpScript Full path to the PHP application to start.
	 * @param string|string[]|null $args List of arguments to pass to the application.
	 */
	public static function launchPhpTask($pathPhpScript, $args = null)
	{
		$formattedArgs = array();
		if(is_array($args))
		{
			foreach($args as $arg)
				$formattedArgs[] = escapeshellarg($arg);
		}
		elseif(is_string($args))
			$formattedArgs[] = escapeshellarg($args);

		if(class_exists('COM'))
		{
			// windows
			$shell = sprintf('start php -f %s -- %s', escapeshellarg($pathPhpScript), join(' ', $formattedArgs));
		}
		else
		{
			// unix
			$shell = sprintf('php -f %s -- %s >/dev/null &', escapeshellarg($pathPhpScript), join(' ', $formattedArgs));
		}
		exec($shell);
	}
}
