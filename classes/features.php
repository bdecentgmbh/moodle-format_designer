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
 * Designer feature registry.
 *
 * Single source of truth for the "major features" an administrator can enable or
 * disable from the Manage features admin page. The free plugin owns the mechanism;
 * the pro plugin (local_designer) contributes its own definitions through
 * \local_designer\features::get_features().
 *
 * @package   format_designer
 * @copyright 2024 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_designer;

/**
 * Registry of the designer course format major features.
 */
class features {

    /** Feature group: course-wide behaviour. */
    const GROUP_COURSE = 'course';

    /** Feature group: section presentation. */
    const GROUP_SECTION = 'section';

    /** Feature group: activity presentation. */
    const GROUP_ACTIVITY = 'activity';

    /** Feature group: course header. */
    const GROUP_HEADER = 'header';

    /**
     * Return the canonical list of major features, keyed by feature key.
     *
     * Each entry contains:
     *  - name        : lang string key (in format_designer) for the toggle label.
     *  - description : lang string key for the toggle description.
     *  - group       : one of the GROUP_* constants, used to group the admin page.
     *  - pro         : true when the feature only applies under Designer Pro.
     *  - depends     : optional frankenstyle component the feature requires (null if none).
     *  - courseoptions   : per-course option keys owned by the feature (course_format_options).
     *  - activityoptions : per-activity designer_<x> option keys owned by the feature.
     *  - configkeys      : global format_designer/<key> settings owned by the feature.
     *
     * @return array
     */
    public static function get_features(): array {
        $features = [
            'coursetype' => [
                'name' => 'feature_coursetype',
                'description' => 'feature_coursetype_desc',
                'group' => self::GROUP_COURSE,
                'pro' => false,
                'depends' => null,
                'courseoptions' => [
                    'coursetype', 'showanimation', 'flowsize', 'listwidth', 'initialstate',
                ],
                'activityoptions' => [],
                'configkeys' => ['flowanimationduration'],
            ],
            'accordion' => [
                'name' => 'feature_accordion',
                'description' => 'feature_accordion_desc',
                'group' => self::GROUP_COURSE,
                'pro' => false,
                'depends' => null,
                'courseoptions' => ['accordion', 'initialstate'],
                'activityoptions' => [],
                'configkeys' => [],
            ],
            'sectionactivitylayout' => [
                'name' => 'feature_sectionactivitylayout',
                'description' => 'feature_sectionactivitylayout_desc',
                'group' => self::GROUP_SECTION,
                'pro' => false,
                'depends' => null,
                'courseoptions' => [],
                'activityoptions' => [],
                'configkeys' => ['sectiontype'],
            ],
            'coursesectionlayout' => [
                'name' => 'feature_coursesectionlayout',
                'description' => 'feature_coursesectionlayout_desc',
                'group' => self::GROUP_SECTION,
                'pro' => false,
                'depends' => null,
                'courseoptions' => [],
                'activityoptions' => [],
                'configkeys' => ['desktopwidth', 'tabletwidth', 'mobilewidth'],
            ],
            'heroactivity' => [
                'name' => 'feature_heroactivity',
                'description' => 'feature_heroactivity_desc',
                'group' => self::GROUP_ACTIVITY,
                'pro' => false,
                'depends' => null,
                'courseoptions' => [
                    'courseheroactivityheader', 'sectionzeroactivities', 'heroactivity', 'heroactivitypos',
                ],
                'activityoptions' => ['heroactivity', 'heroactivitypos'],
                'configkeys' => [
                    'sectionzeroactivities', 'heroactivity', 'heroactivitypos', 'avoidduplicate_heromodentry',
                ],
            ],
            'courseheader' => [
                'name' => 'feature_courseheader',
                'description' => 'feature_courseheader_desc',
                'group' => self::GROUP_HEADER,
                'pro' => false,
                'depends' => null,
                'courseoptions' => ['courseheader', 'coursestaff', 'coursestafflayout'],
                'activityoptions' => [],
                'configkeys' => [],
            ],
            'activityprogress' => [
                'name' => 'feature_activityprogress',
                'description' => 'feature_activityprogress_desc',
                'group' => self::GROUP_HEADER,
                'pro' => false,
                'depends' => null,
                'courseoptions' => ['activityprogress'],
                'activityoptions' => [],
                'configkeys' => [],
            ],
            'timemanagement' => [
                'name' => 'feature_timemanagement',
                'description' => 'feature_timemanagement_desc',
                'group' => self::GROUP_HEADER,
                'pro' => false,
                // No hard dependency: enrolment and completion dates work without the
                // timetable tool; the due-date pieces remain guarded by timetable_installed().
                'depends' => null,
                'courseoptions' => ['timemanagement'],
                'activityoptions' => [],
                'configkeys' => [],
            ],
            'activityelements' => [
                'name' => 'feature_activityelements',
                'description' => 'feature_activityelements_desc',
                'group' => self::GROUP_ACTIVITY,
                'pro' => false,
                'depends' => null,
                'courseoptions' => [],
                'activityoptions' => [
                    'icon', 'visits', 'calltoaction', 'title', 'description', 'modname', 'completionbadge',
                ],
                'configkeys' => [
                    'activitydesclength', 'modtrimlength',
                ],
            ],
            'secondarynav' => [
                'name' => 'feature_secondarynav',
                'description' => 'feature_secondarynav_desc',
                'group' => self::GROUP_ACTIVITY,
                'pro' => false,
                'depends' => null,
                'courseoptions' => ['secondarymenutocourse'],
                'activityoptions' => [
                    'secondarytype', 'secondarycustomtitle', 'customtitleusecourseindex', 'customtitleuseactivityitem',
                ],
                'configkeys' => [],
            ],
            'courseindex' => [
                'name' => 'feature_courseindex',
                'description' => 'feature_courseindex_desc',
                'group' => self::GROUP_COURSE,
                'pro' => false,
                'depends' => null,
                'courseoptions' => ['courseindex'],
                'activityoptions' => [],
                'configkeys' => [],
            ],
            'popupactivities' => [
                'name' => 'feature_popupactivities',
                'description' => 'feature_popupactivities_desc',
                'group' => self::GROUP_ACTIVITY,
                'pro' => false,
                'depends' => 'format_popups',
                'courseoptions' => ['popupactivities', 'addnavigation'],
                'activityoptions' => [],
                'configkeys' => [],
            ],
        ];

        // Let Designer Pro contribute its own feature definitions.
        if (helper::has_pro()
                && class_exists('\local_designer\features')
                && method_exists('\local_designer\features', 'get_features')) {
            $features += \local_designer\features::get_features();
        }

        return $features;
    }

    /**
     * Return the option keys (of the given metadata type) that should be removed because
     * their owning feature is disabled.
     *
     * A key is only removed when it is owned exclusively by disabled features. If any
     * enabled feature also lists the key (overlapping ownership, e.g. initialstate is
     * shared by the coursetype and accordion features) it is kept.
     *
     * @param string $type One of 'courseoptions', 'activityoptions' or 'configkeys'.
     * @return string[] Flat list of option keys to remove.
     */
    public static function disabled_option_keys(string $type): array {
        $enabled = [];
        $disabled = [];
        foreach (self::get_features() as $key => $def) {
            $keys = $def[$type] ?? [];
            if (helper::feature_enabled($key)) {
                $enabled = array_merge($enabled, $keys);
            } else {
                $disabled = array_merge($disabled, $keys);
            }
        }
        return array_values(array_diff(array_unique($disabled), array_unique($enabled)));
    }
}
