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
 * Global settings page.
 *
 * @package   format_designer
 * @copyright 2021 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/course/format/designer/lib.php');

if ($ADMIN->fulltree) {
    $settings = new theme_boost_admin_settingspage_tabs('formatsettingdesigner', get_string('configtitle', 'format_designer'));

    $page = new admin_settingpage('format_designer_general', get_string('general', 'format_designer'));

    $page->add(
        new admin_setting_configselect('format_designer/dateformat',
        new lang_string('dateformat', 'format_designer'),
        new lang_string('dateformat_help', 'format_designer'),
        'monthandday', [
            'usstandarddate' => userdate(time(), get_string('usstandarddate', 'format_designer')),
            'monthandday' => userdate(time(), get_string('monthandday', 'format_designer')),
            'strftimedate' => userdate(time(), get_string('strftimedate')),
            'strftimedatefullshort' => userdate(time(), get_string('strftimedatefullshort')),
            'strftimedateshort' => userdate(time(), get_string('strftimedateshort')),
            'strftimedatetime' => userdate(time(), get_string('strftimedatetime')),
            'strftimedatetimeshort' => userdate(time(), get_string('strftimedatetimeshort')),
            'strftimedaydate' => userdate(time(), get_string('strftimedaydate')),
            'strftimedaydatetime' => userdate(time(), get_string('strftimedaydatetime')),
            'strftimedayshort' => userdate(time(), get_string('strftimedayshort')),
            'strftimedaytime' => userdate(time(), get_string('strftimedaytime')),
            'strftimemonthyear' => userdate(time(), get_string('strftimemonthyear')),
            'strftimerecent' => userdate(time(), get_string('strftimerecent')),
            'strftimerecentfull' => userdate(time(), get_string('strftimerecentfull')),
        ]
    ));

    $page->add(
        new admin_setting_configtext('format_designer/flowanimationduration',
        new lang_string('flowanimationduration', 'format_designer'),
        new lang_string('flowanimationduration_help', 'format_designer'),
        '0.5', PARAM_FLOAT
        )
    );

    $settings->add($page);

    if (format_designer_has_pro()
         && file_exists($CFG->dirroot.'/local/designer/setting.php')) {
        require_once($CFG->dirroot.'/local/designer/setting.php');
    }
}
