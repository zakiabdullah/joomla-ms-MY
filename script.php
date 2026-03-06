<?php

/**
 * @package    Joomla.Language
 *
 * @copyright  (C) 2026 Joomla! Malaysia <https://www.joomla.my>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Installer\InstallerScript;

/**
 * Installation class to perform additional changes during install/uninstall/update
 *
 * @since  6.0.0v1
 */
class Pkg_msMYInstallerScript extends InstallerScript
{
	/**
	 * Extension script constructor.
	 *
	 * @since   6.0.0v1
	 */
	public function __construct()
	{
		$this->minimumJoomla = '6.0';
		$this->minimumPhp    = '8.2.0';
	}
}
