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

if (version_compare($CFG->version, '2024100700', '<')) {
    require_once($CFG->dirroot.'/cache/classes/loaders.php');
}

/**
 * Custom cache loader to handle the smart menus and items deletion.
 */
class loader extends \cache_application {

    /**
     * Delete the cached menus or menu items for all of its users.
     *
     * Fetch the cache store, generate the keys with menu or item id and keyword of user cache.
     * Get the list of cached files by their filename, filenames are stored in the format of "menuid/itemid_u_ userid".
     * Generate the key with menu/item id and the keyword of "_u" to get list of all users cache file for this menu/item.
     *
     * Delete all the files using delete_many method.
     *
     * @param int $id ID of the menu or item.
     * @return void
     */
    public function delete_vaild_section_completed_cache($courseid, $sectionid = 0) {
        $store = $this->get_store();
        $prefix = "v_s_c_c_{$courseid}";
        if ($sectionid) {
            $prefix .= "_s_{$sectionid}";
        }
        if ($list = $store->find_by_prefix($prefix)) {
            $keys = array_map(function($key) {
                $key = current(explode('-', $key));
                return $key;
            }, $list);
            $this->delete_many($keys);
        }
    }

    /**
     * Delete user section completed cache.
     * @param mixed $courseid
     * @param mixed $sectionid
     * @param mixed $userid
     * @return void
     */
    public function delete_user_section_completed_cache($courseid, $sectionid = 0, $userid = 0) {
        $prefix = "s_c_c_{$courseid}";
        $this->delete_prefix_cache($prefix);
    }

    /**
     * Delete due overdue activities count.
     * @param mixed $courseid
     * @param mixed $userid
     * @return void
     */
    public function delete_due_overdue_activities_count($courseid, $userid = 0) {
        $prefix = "d_o_a_c_c{$courseid}";
        if ($userid) {
            $prefix .= "_u{$userid}";
        }
        $this->delete_prefix_cache($prefix);
    }

    /**
     * Delete course progress uncompletion criteria.
     * @param mixed $courseid
     * @param mixed $userid
     * @return void
     */
    public function delete_course_progress_uncompletion_criteria($courseid, $userid = 0) {
        $prefix = "u_c_c_s{$courseid}";
        if ($userid) {
            $prefix .= "_u{$userid}";
        }
        $this->delete_prefix_cache($prefix);
    }

    /**
     * Delete_criteria_progress.
     * @param mixed $courseid
     * @param mixed $userid
     * @return void
     */
    public function delete_criteria_progress($courseid, $userid = 0) {
        $prefix = "c_p_c{$courseid}";
        if ($userid) {
            $prefix .= "_u_{$userid}";
        }
        $this->delete_prefix_cache($prefix);
    }

    /**
     * Delete_prerequisites_courses.
     * @return void
     */
    public function delete_prerequisites_courses() {
        $prefix = "data_prereq_main_c";
        $this->delete_prefix_cache($prefix);
    }

    /**
     * Delete the cache files by the prefix.
     * @param mixed $prefix
     * @return void
     */
    public function delete_prefix_cache($prefix) {
        $store = $this->get_store();
        if ($list = $store->find_by_prefix($prefix)) {
            $keys = array_map(function($key) {
                $key = current(explode('-', $key));
                return $key;
            }, $list);
            $this->delete_many($keys);
        }
    }
}
