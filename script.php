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
 * @since  5.4.4v1
 */
class Pkg_msMYInstallerScript extends InstallerScript
{
	/**
	 * Extension script constructor.
	 *
	 * @since   5.4.4v1
	 */
	public function __construct()
	{
		$this->minimumJoomla = '5.0';
		$this->minimumPhp    = '8.1.0';
	}
}
