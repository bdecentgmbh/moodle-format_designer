<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Format Designer - Custom cache loader for the smart menus.
 *
 * @package    format_designer
 * @copyright  2023 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_designer\cache;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once(__DIR__ . '/loader_trait.php');

if (!class_exists('\core_cache\application_cache')) {
    require_once($CFG->dirroot . '/cache/classes/loaders.php');
    /**
     * Custom cache loader to handle the smart menus and items deletion for older Moodle versions.
     */
    class loader extends \cache_application {
        use loader_trait;
    }
} else {
    // For newer Moodle versions, use the loader_newer class and alias it.
    require_once(__DIR__ . '/loader_newer.php');
    // Use class_alias to create the loader class with the correct parent.
    class_alias('format_designer\cache\loader_newer', 'format_designer\cache\loader');
}
