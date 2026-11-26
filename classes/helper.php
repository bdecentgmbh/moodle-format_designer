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
 * Designer helper class for utility functions.
 *
 * This helper class provides common utility functions that can be used by other files
 * in the plugin (renderer, classes, etc.) WITHOUT loading lib.php.
 * This improves performance since lib.php is loaded on every course page.
 *
 * @package   format_designer
 * @copyright 2021 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_designer;

use stdClass;
use context_course;
use core_component;
use cache;

/**
 * Course helper class with utility methods.
 */
class helper {
    /**
     * Instance for the helper class.
     *
     * @var \instance
     */
    public static $instance;

    /**
     * Course record.
     *
     * @var \stdclass
     */
    public $course;

    /**
     * Cache for section layout types. Format: ['sectionid' => 'layouttype']
     *
     * @var array
     */
    private static $sectionlayoutcache = [];

    /**
     * Shared static cache for all background images across all methods.
     *
     * @var array
     */
    private static $backgroundcache = [];

    /**
     * Track which courses have been preloaded.
     *
     * @var array
     */
    private static $preloadedcourses = [];

    /**
     * Create instance of helper class.
     *
     * @param \stdClass $course
     * @return self
     */
    public static function create($course = null) {
        // Create this class instance.
        if (self::$instance == null) {
            self::$instance = new self();
        }
        // Self instance.
        self::$instance->set_course($course);

        return self::$instance;
    }

    /**
     * Set the course.
     *
     * @param stdclass $course
     */
    public function set_course($course) {
        $this->course = $course;
    }

    /**
     * Get the course header staffs.
     *
     * @param object $course
     * @return array data
     */
    public function get_course_staff_users($course) {
        global $PAGE, $DB, $USER;
        $staffs = [];
        $i = 1;

        // Course staff roles are not setup, exit here.
        if (!isset($course->coursestaff)) {
            return [];
        }

        // Course context.
        $coursecontext = \context_course::instance($course->id);

        // List of staff users ids for the course.
        $staffids = $this->get_staffs_users($course);

        // Staff users are not found.
        if (empty($staffids)) {
            return [];
        }
        foreach ($staffids as $userid) {
            $customfield = [];
            $user = \core_user::get_user($userid);

            if (self::has_pro() != 1) {
                $extrafields = profile_get_user_fields_with_data($userid);
                $fieldcontext = \context_user::instance($userid);
                foreach ($extrafields as $formfield) {
                    // Selected for the course, and visible to the current user by the field's visibility setting.
                    if (!empty($course->{$formfield->inputname}) && $formfield->show_field_content()) {
                        $customfield[]['value'] = self::format_user_profile_field($formfield, $fieldcontext);
                    }
                }
            }

            $roles = get_user_roles($coursecontext, $userid, false);
            array_map(function ($role) {
                $role->name = role_get_name($role);
                return $role;
            }, $roles);
            $roles = implode(", ", array_column($roles, 'name'));

            // Users data list. Only the fields the header uses, not the user record.
            $list = new stdClass();
            $list->userid = $userid;
            // The email address only when the current user may see it, e.g. by the user's email display setting.
            $list->email = self::get_visible_user_details($user, $course, ['email'])['email'] ?? '';
            $list->fullname = fullname($user);
            // Contact and profile view url.
            $list->profileurl = new \moodle_url('/user/profile.php', ['id' => $userid]);
            $list->contacturl = new \moodle_url('/message/index.php', ['id' => $userid]);
            // User picture.
            $userpicture = new \user_picture($user);
            $userpicture->size = 1; // Size f1.
            $list->profileimageurl = $userpicture->get_url($PAGE)->out(false);

            $list->role = $roles; // List of roles, user assigned to.
            // Message and contact buttons, as far as the current user can use them.
            foreach (self::staff_contact_actions($userid) as $key => $value) {
                $list->$key = $value;
            }
            // List of custom fields.
            $list->customfield = $customfield;
            // Add active class to the first user for carousel.
            $list->active = ($i == 1) ? true : false;

            $staffs[] = $list; // Attach to staffs list.
            $i++;
        }
        return $staffs;
    }

    /**
     * The message and contact buttons of a staff user that the current user can use.
     *
     * Moodle only lets users message, or send contact requests to, people they share a course with, unless the site
     * allows messages to all users. So a user who views the course through guest access or isn't enrolled would get
     * "Can not contact user" from the buttons. They are left out when they wouldn't work.
     *
     * @param int $staffid Staff user id.
     * @return array cansendmessage, showaddtocontacts, hascontactactions, iscontact and contacttitle.
     */
    public static function staff_contact_actions(int $staffid): array {
        global $USER;

        $isself = $USER->id == $staffid;
        $iscontact = \core_message\api::is_contact($USER->id, $staffid);
        $cansendmessage = !$isself && isloggedin() && !isguestuser() &&
            \core_message\api::can_send_message($staffid, $USER->id);
        // An existing contact can always be removed.
        $showaddtocontacts = !$isself && isloggedin() && !isguestuser() &&
            ($iscontact || \core_message\api::can_create_contact($USER->id, $staffid));
        return [
            'cansendmessage' => $cansendmessage,
            'showaddtocontacts' => $showaddtocontacts,
            'hascontactactions' => $cansendmessage || $showaddtocontacts,
            'iscontact' => $iscontact,
            'contacttitle' => $iscontact ? get_string('removefromyourcontacts', 'message') :
                get_string('addtoyourcontacts', 'message'),
        ];
    }

    /**
     * Get the details of a user that the current user may see in a course.
     *
     * Uses the core rules, which for example show the email address only when the user's
     * email display setting, the hidden user fields and the viewer's capabilities allow it.
     *
     * @param stdClass $user User record.
     * @param stdClass $course Course the details are shown in.
     * @param string[] $fields Fields to get, from the core list of user detail fields.
     * @return array Visible values by field name, always including id and fullname when the user is visible.
     */
    public static function get_visible_user_details(stdClass $user, stdClass $course, array $fields): array {
        global $CFG;

        if (empty($fields)) {
            // Core returns all fields for an empty list.
            return [];
        }
        if (method_exists(\core\user::class, 'get_user_details')) {
            // Moodle 5.3 and later, user_get_user_details() is deprecated there.
            $details = \core\user::get_user_details($user, $course, $fields);
        } else {
            require_once($CFG->dirroot . '/user/lib.php');
            $details = user_get_user_details($user, $course, $fields);
        }
        return $details ?? [];
    }

    /**
     * Format one profile field for the course staff header.
     *
     * Textarea values are stored unfiltered, so they are cleaned with format_text()
     * in the profile owner's user context before the template renders them.
     * Other field types use display_data(), which applies format_string() or
     * format_text() for that type.
     *
     * @param \profile_field_base $formfield Field with this user's data loaded.
     * @param \context $context User context of the field owner.
     * @return string HTML safe to render.
     */
    private static function format_user_profile_field($formfield, \context $context): string {
        $datatype = $formfield->field->datatype ?? '';
        if ($datatype === 'textarea') {
            $format = $formfield->dataformat ?? FORMAT_MOODLE;
            if ($format === '') {
                $format = FORMAT_MOODLE;
            }
            return format_text((string) ($formfield->data ?? ''), (int) $format, [
                'context' => $context,
                'overflowdiv' => true,
            ]);
        }
        return (string) $formfield->display_data();
    }

    /**
     * Bulk preload all section background images for a course.
     * @param stdClass $course Course object.
     * @param \course_modinfo|null $modinfo Course module info object or null to load it.
     * @return void
     */
    public static function preload_section_backgrounds($course, $modinfo) {
        global $DB;

        // Check if already loaded.
        if (isset(self::$preloadedcourses[$course->id])) {
            return;
        }

        // If modinfo is null, load it.
        if ($modinfo === null) {
            $modinfo = get_fast_modinfo($course);
        }

        // Additional null check for safety.
        if (!$modinfo) {
            self::$preloadedcourses[$course->id] = true;
            return;
        }

        $coursecontext = context_course::instance($course->id);
        $sections = $modinfo->get_section_info_all();

        if (empty($sections)) {
            self::$preloadedcourses[$course->id] = true;
            return;
        }

        $format = course_get_format($course);

        // Get all section IDs that have background images.
        $sectionids = [];
        foreach ($sections as $section) {
            $hasbackground = $format->get_section_option($section->id, 'sectiondesignerbackgroundimage') ?? null;
            if (!empty($hasbackground)) {
                $sectionids[] = $section->id;
            }
        }

        if (empty($sectionids)) {
            self::$preloadedcourses[$course->id] = true;
            return;
        }

        // ONE SQL query to load ALL background files.
        [$insql, $params] = $DB->get_in_or_equal($sectionids, SQL_PARAMS_NAMED);
        $params['contextid'] = $coursecontext->id;
        $params['component'] = 'format_designer';

        $sql = "SELECT f.id, f.itemid, f.filearea, f.contextid, f.component,
                    f.filepath, f.filename
                FROM {files} f
                WHERE f.contextid = :contextid
                AND f.component = :component
                AND f.filearea IN ('sectiondesignbackground', 'sectiondesigncompletionbackground')
                AND f.itemid $insql
                AND f.filename != '.'
                ORDER BY f.itemid, f.filearea";

        $files = $DB->get_records_sql($sql, $params);

        // Pre-populate cache.
        foreach ($files as $file) {
            $fileurl = \moodle_url::make_pluginfile_url(
                $file->contextid,
                $file->component,
                $file->filearea,
                $file->itemid,
                $file->filepath,
                $file->filename,
                false
            );

            $cachekey = $file->itemid . '_' . $course->id . '_' . $file->filearea;
            self::$backgroundcache[$cachekey] = $fileurl->out(false);
        }

        // Mark sections without backgrounds as empty to avoid fallback queries.
        foreach ($sectionids as $sectionid) {
            $basecachekey = $sectionid . '_' . $course->id;
            if (
                !isset(self::$backgroundcache[$basecachekey . '_sectiondesignbackground']) &&
                !isset(self::$backgroundcache[$basecachekey . '_sectiondesigncompletionbackground'])
            ) {
                self::$backgroundcache[$basecachekey . '_sectiondesignbackground'] = '';
                self::$backgroundcache[$basecachekey . '_sectiondesigncompletionbackground'] = '';
            }
        }

        self::$preloadedcourses[$course->id] = true;
    }

    /**
     * Get course staff users.
     *
     * @param object $course
     * @return array userids
     */
    protected function get_staffs_users($course) {
        $staffids = [];
        if (!empty($course->coursestaff)) {
            $staffroleids = explode(",", $course->coursestaff);
            $coursecontext = \context_course::instance($course->id);
            $staffids = get_role_users($staffroleids, $coursecontext, false, 'ra.id, u.lastname, u.firstname, u.id');
        }
        return array_keys($staffids);
    }

    /**
     * Format date based on format defined in settings.
     *
     * @param int $timestamp
     * @return string
     */
    public static function format_date(int $timestamp): string {
        if ($format = get_config('format_designer', 'dateformat')) {
            $component = strpos($format, 'strf') === 0 ? '' : 'format_designer';
        } else {
            $format = 'usstandarddate';
            $component = 'format_designer';
        }

        return userdate($timestamp, get_string($format, $component));
    }

    /**
     * Split a text into its words.
     *
     * Any white space separates two words, including the non-breaking spaces the editor stores as &nbsp;.
     * Digits and non-ASCII letters belong to the words, unlike in str_word_count().
     *
     * @param string $str Text, as returned by format_string().
     * @return string[] The words.
     */
    public static function get_words(string $str): array {
        return preg_split('/(?:\s|\x{00A0}|&nbsp;|&#160;|&#xa0;)+/iu', $str, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Cut the Course content.
     *
     * @param string $str String to trim.
     * @param int $n Number of words to keep.
     * @return string
     */
    public static function modcontent_trim_char(string $str, int $n = 25): string {
        $words = self::get_words($str);
        if (count($words) <= $n) {
            return $str;
        }
        return implode(' ', array_slice($words, 0, $n)) . '...';
    }

    /**
     * Oldest Designer Pro (local_designer) version this format works with: Pro 1.7 R5.
     *
     * Raise it whenever a function, constant or class shared with Pro changes.
     */
    public const MIN_PRO_VERSION = 2026050201;

    /**
     * Release name shown in the "Designer Pro is outdated" notice.
     */
    public const MIN_PRO_RELEASE = '1.8';

    /**
     * Check if a usable Designer Pro is installed.
     *
     * Pro only counts as installed when its installed version is at least MIN_PRO_VERSION.
     * An older Pro (for example while only the format has been updated) is ignored,
     * so that Designer courses and Site administration keep working until Pro is updated too.
     *
     * @return bool
     */
    public static function has_pro(): bool {
        return self::pro_present() && !self::pro_outdated();
    }

    /**
     * Check if the Designer Pro code is present, regardless of its version.
     *
     * @return bool
     */
    public static function pro_present(): bool {
        return array_key_exists('designer', core_component::get_plugin_list('local'));
    }

    /**
     * Check if Designer Pro is present but older than the version this format needs.
     *
     * Uses the installed (database) version, so a Pro whose new code isn't upgraded yet also counts as outdated.
     *
     * @return bool
     */
    public static function pro_outdated(): bool {
        if (!self::pro_present()) {
            return false;
        }
        $version = get_config('local_designer', 'version');
        return !$version || (int) $version < self::MIN_PRO_VERSION;
    }

    /**
     * Get the designer format custom layouts.
     *
     * @return array list of available module pro layouts.
     */
    public static function get_pro_layouts(): array {
        return array_keys(core_component::get_plugin_list('layouts'));
    }

    /**
     * Get the designer format custom layouts.
     *
     * @return array
     */
    public static function get_all_layouts(): array {
        $layouts = [
            'default' => get_string('link', 'format_designer'),
            'list' => get_string('list', 'format_designer'),
            'cards' => get_string('cards', 'format_designer'),
        ];
        $prolayouts = array_keys(core_component::get_plugin_list('layouts'));
        $prolayouts = (array) get_strings($prolayouts, 'format_designer');
        return array_merge($layouts, $prolayouts);
    }


    /**
     * Get section background image url.
     *
     * @param \section_info $section section info class instance.
     * @param stdclass $course Course record object.
     * @param \course_modinfo $modinfo Course module info class instance.
     * @return string Section background image URL.
     */
    public static function get_section_background_image($section, $course, $modinfo): string {
        $basecachekey = $section->id . '_' . $course->id;

        if (empty(self::$backgroundcache)) {
            self::preload_section_backgrounds($course, $modinfo);
        }

        $format = course_get_format($section->course);
        $sectiondesignerbackgroundimage = $format->get_section_option($section->id, 'sectiondesignerbackgroundimage') ?? null;
        if (empty($sectiondesignerbackgroundimage)) {
            return '';
        }

        // Determine filearea.
        $filearea = 'sectiondesignbackground';
        $realtiveactivities = isset($course->calsectionprogress) &&
            ($course->calsectionprogress == DESIGNER_PROGRESS_RELEVANTACTIVITIES) ? true : false;

        if (
            \format_designer\options::is_section_completed($section, $course, $modinfo, true, $realtiveactivities)
            && (isset($section->sectiondesignerusecompletionbg) && $section->sectiondesignerusecompletionbg)
        ) {
            $filearea = 'sectiondesigncompletionbackground';
        }

        $cachekey = $basecachekey . '_' . $filearea;
        // Return from preloaded cache.
        if (isset(self::$backgroundcache[$cachekey])) {
            return self::$backgroundcache[$cachekey];
        }
        // If not in cache (shouldn't happen if preload was called), return empty.
        return '';
    }


    /**
     * Get modules layout class.
     *
     * @param object $format Course format object.
     * @param \section_info $section Section info.
     * @return string Layout class.
     */
    public static function get_module_layoutclass($format, $section): string {
        // Cache section type.
        if (!isset(self::$sectionlayoutcache[$section->id])) {
            self::$sectionlayoutcache[$section->id] = $format->get_section_option($section->id, 'sectiontype')
                ?: get_config('format_designer', 'sectiontype');
        }

        $sectiontype = self::$sectionlayoutcache[$section->id];

        $sectionlayoutclass = '';

        if ($sectiontype == 'list') {
            $sectionlayoutclass = " position-relative ";
        } else if ($sectiontype == 'cards') {
            $sectionlayoutclass = ' card ';
        }

        if ($format->get_course()->coursetype == DESIGNER_TYPE_FLOW) {
            $sectionlayoutclass = 'card';
            $sectiontype = 'cards';
        }

        $prolayouts = self::get_pro_layouts();
        if (in_array($sectiontype, $prolayouts)) {
            if (self::has_pro()) {
                if ($sectiontype == 'circles') {
                    $sectionlayoutclass = ' circle-layout card ';
                } else if ($sectiontype == 'horizontal_circles') {
                    $sectionlayoutclass = ' horizontal_circles circle-layout card ';
                }
            }
        }

        return $sectionlayoutclass;
    }

    /**
     * Find the plugin format_popup installed.
     *
     * @return bool
     */
    public static function popup_installed(): bool {
        $pluginman = \core_plugin_manager::instance();
        $plugininfo = $pluginman->get_plugin_info('format_popups');
        return !empty($plugininfo);
    }

    /**
     * Check course has heroactivity condition or not.
     *
     * @param object $course Course object.
     * @return bool
     */
    public static function course_has_heroactivity($course): bool {
        global $DB, $PAGE;
        $iscourseheroactivity = ($course->sectionzeroactivities &&
            $course->heroactivity == DESIGNER_HERO_ACTIVITY_EVERYWHERE) ? true : false;
        $sql = "SELECT fd.value FROM {format_designer_options} fd
            WHERE fd.courseid = :courseid AND fd.name = :optionname AND fd.value = :optionvalue AND fd.cmid != :currentcm";
        $iscoursemodheroactivity = $DB->record_exists_sql($sql, [
            'optionname' => 'heroactivity',
            'optionvalue' => 1,
            'courseid' => $course->id,
            'currentcm' => $PAGE->cm->id ?? 0,
        ]);
        return ($iscourseheroactivity || $iscoursemodheroactivity) ? true : false;
    }

    /**
     * Per-request cache of the Video Time records the course page needs, indexed by course id.
     *
     * @var array
     */
    protected static $videotimerecords = [];

    /**
     * Preload, once per request and course, the Video Time related records the course page needs.
     *
     * Every Video Time activity on the course page used to fetch its own videotime, videotimeplugin_pro and
     * videotime_vimeo_video record, i.e. three to four queries per activity. On a course with 100+ videos that is
     * several hundred queries, and the videotime_vimeo_video lookup by link is not indexed, so on sites with a
     * large Vimeo library each of those is a full table scan (DES-962). Loading the records for the whole course
     * in three queries removes that cost. Tables that do not exist (plugin or subplugin not installed) yield
     * empty arrays.
     *
     * @param int $courseid Course id.
     * @return array ['instances' => [instanceid => videotime record],
     *                'pro' => [instanceid => videotimeplugin_pro record],
     *                'vimeo' => [link => videotime_vimeo_video record (id, link, duration)]]
     */
    public static function preload_videotime_records(int $courseid): array {
        global $DB;

        if (isset(self::$videotimerecords[$courseid])) {
            return self::$videotimerecords[$courseid];
        }

        $records = ['instances' => [], 'pro' => [], 'vimeo' => []];
        $dbman = $DB->get_manager();

        if ($dbman->table_exists('videotime')) {
            $records['instances'] = $DB->get_records('videotime', ['course' => $courseid]);
        }

        if (!empty($records['instances'])) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($records['instances']), SQL_PARAMS_NAMED, 'vt');

            if ($dbman->table_exists('videotimeplugin_pro')) {
                $prorecords = $DB->get_records_select('videotimeplugin_pro', "videotime $insql", $params);
                foreach ($prorecords as $prorecord) {
                    $records['pro'][$prorecord->videotime] = $prorecord;
                }
            }

            $links = [];
            foreach ($records['instances'] as $instance) {
                if (!empty($instance->vimeo_url)) {
                    $links[$instance->vimeo_url] = true;
                }
            }
            if (!empty($links) && $dbman->table_exists('videotime_vimeo_video')) {
                [$linksql, $linkparams] = $DB->get_in_or_equal(array_keys($links), SQL_PARAMS_NAMED, 'lnk');
                $videos = $DB->get_records_select(
                    'videotime_vimeo_video',
                    "link $linksql",
                    $linkparams,
                    '',
                    'id, link, duration'
                );
                foreach ($videos as $video) {
                    $records['vimeo'][$video->link] = $video;
                }
            }
        }

        self::$videotimerecords[$courseid] = $records;
        return $records;
    }

    /**
     * Get the videotime record of an activity from the per-request course cache.
     *
     * @param int $courseid Course id.
     * @param int $instanceid Video Time instance id.
     * @return stdClass|null
     */
    public static function get_videotime_instance(int $courseid, int $instanceid): ?stdClass {
        return self::preload_videotime_records($courseid)['instances'][$instanceid] ?? null;
    }

    /**
     * Get the videotimeplugin_pro record of an activity from the per-request course cache.
     *
     * @param int $courseid Course id.
     * @param int $instanceid Video Time instance id.
     * @return stdClass|null
     */
    public static function get_videotime_pro_record(int $courseid, int $instanceid): ?stdClass {
        return self::preload_videotime_records($courseid)['pro'][$instanceid] ?? null;
    }

    /**
     * Get the Vimeo library record (id, link, duration) for a video link from the per-request course cache.
     *
     * @param int $courseid Course id.
     * @param string $link Video URL as stored in the videotime instance.
     * @return stdClass|null
     */
    public static function get_videotime_vimeo_video(int $courseid, string $link): ?stdClass {
        return self::preload_videotime_records($courseid)['vimeo'][$link] ?? null;
    }

    /**
     * Forget the preloaded Video Time records (for tests, or after changing Video Time activities in the request).
     *
     * @param int|null $courseid Course id, or null to clear every course.
     */
    public static function reset_videotime_records(?int $courseid = null): void {
        if ($courseid === null) {
            self::$videotimerecords = [];
        } else {
            unset(self::$videotimerecords[$courseid]);
        }
    }

    /**
     * Course has videotime activity.
     *
     * @param stdClass $course
     * @return bool
     */
    public static function course_has_videotime($course): bool {
        global $DB;
        $pluginman = \core_plugin_manager::instance();
        $plugininfo = $pluginman->get_plugin_info('mod_videotime');
        return !empty($plugininfo) && $DB->record_exists_sql(
            <<<'EOT'
            SELECT
                1
            FROM {modules} m
            JOIN {course_modules} cm
                ON cm.module = m.id
                AND cm.course = ?
            WHERE
                m.name = ?
            EOT,
            [ $course->id, 'videotime' ]
        );
    }

    /**
     * Set the section zero to hero activities.
     *
     * @param array $reports Reports array.
     * @param object $course Course object.
     * @return array Updated reports.
     */
    public static function section_zero_tomake_hero($reports, $course): array {
        global $PAGE, $DB;
        $course = course_get_format($course->id)->get_course();
        if ($course->sectionzeroactivities) {
            $modinfo = get_fast_modinfo($course);
            if (isset($modinfo->sections[0])) {
                foreach ($modinfo->sections[0] as $modnumber) {
                    if ($DB->record_exists('course_modules', ['deletioninprogress' => 0, 'id' => $modnumber])) {
                        if (isset($reports[$modnumber]) && !$reports[$modnumber]['heroactivity']) {
                            $reports[$modnumber]['heroactivity'] = ($course->heroactivity == DESIGNER_HERO_ACTIVITY_COURSEPAGE
                                && isset($PAGE->cm->id)) ? 0 : ($course->heroactivity == true);
                            $reports[$modnumber]['heroactivitypos'] = $course->heroactivitypos;
                        } else if (!isset($reports[$modnumber])) {
                            $reports[$modnumber]['heroactivity'] = ($course->heroactivity == DESIGNER_HERO_ACTIVITY_COURSEPAGE
                                && isset($PAGE->cm->id)) ? 0 : ($course->heroactivity == true);
                            $reports[$modnumber]['heroactivitypos'] = $course->heroactivitypos;
                            $reports[$modnumber]['cmid'] = $modnumber;
                        }
                    }
                }
            }
        }
        return $reports;
    }

    /**
     * Get course type options.
     *
     * @return array Course types.
     */
    public static function get_coursetypes(): array {
        return [
            0 => get_string('normal'),
            DESIGNER_TYPE_KANBAN => get_string('kanbanboard', 'format_designer'),
            DESIGNER_TYPE_COLLAPSIBLE => get_string('collapsiblesections', 'format_designer'),
            DESIGNER_TYPE_FLOW => get_string('type_flow', 'format_designer'),
        ];
    }

    /**
     * Update the custom or other selected values.
     *
     * @param object $data Data object.
     * @param string $name Field name.
     * @param string $custom Custom field name.
     * @param string $csselement CSS element name.
     * @return string CSS value or empty string.
     */
    public static function fill_custom_values($data, string $name, string $custom, string $csselement): string {
        if ((isset($data->{$name}) && $data->{$name})) {
            if ($data->{$name} == 'custom') {
                $value = $data->{$custom};
            } else {
                $value = $data->{$name};
            }
            if ($csselement) {
                return sprintf("$csselement: %s;", $value);
            } else {
                return $value;
            }
        }
        return "";
    }

    /**
     * Check the subpanel class exists or not.
     *
     * @return bool
     */
    public static function is_support_subpanel(): bool {
        return class_exists('\core\output\local\action_menu\subpanel');
    }

    /**
     * Get cache object for designer options.
     *
     * @return \cache_application|\cache_session|\cache_store
     */
    public static function get_cache_object() {
        return cache::make('format_designer', 'designeroptions');
    }

    /**
     * Find the timetable tool installed.
     *
     * @return bool Result of the timetable plugin availability.
     */
    public static function timetable_installed(): bool {
        global $CFG;
        static $result;

        if ($result === null) {
            if (array_key_exists('timetable', core_component::get_plugin_list('tool'))) {
                require_once($CFG->dirroot . '/admin/tool/timetable/classes/time_management.php');
                $result = true;
            } else {
                $result = false;
            }
        }

        return $result;
    }
}
