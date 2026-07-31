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
 * Tests for what the Manage features upgrade does to an existing site.
 *
 * @package   format_designer
 * @copyright 2026 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_designer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/format/designer/db/upgrade.php');
require_once($CFG->libdir . '/upgradelib.php');

/**
 * Upgrade behaviour for sites that already run Designer.
 *
 * The upgrade must be invisible: an existing course has to look exactly the same the
 * day after the upgrade as it did the day before. These tests pin that promise.
 *
 * @covers ::xmldb_format_designer_upgrade
 */
final class upgrade_test extends \advanced_testcase {
    /** Version that introduced the "plain" global default. */
    const VERSION_PLAIN_DEFAULT = 2026052800;

    /** Version that split the sectionlayouts feature in two. */
    const VERSION_FEATURE_SPLIT = 2026052900;

    /**
     * Run the plugin upgrade from a given starting version, with savepoints suppressed.
     *
     * upgrade_plugin_savepoint() writes to the plugin version and calls upgrade_log(),
     * which needs the upgrade running state. Wrap it the way core's own upgrade tests do.
     *
     * @param int $fromversion
     */
    protected function run_upgrade_from(int $fromversion): void {
        global $CFG;

        // Core's upgrade_plugin_savepoint() refuses to move the recorded version backwards, so wind
        // the plugin's stored version back to where the site under test would be first.
        set_config('version', $fromversion, 'format_designer');

        $CFG->upgraderunning = time() + 300;
        try {
            xmldb_format_designer_upgrade($fromversion);
        } finally {
            unset($CFG->upgraderunning);
        }
    }

    /**
     * A site that already chose a global section layout must keep it. This is the
     * important one: the shipped default changes to "plain", and silently adopting it
     * would restyle every section of every existing course.
     */
    public function test_existing_global_section_layout_is_preserved(): void {
        $this->resetAfterTest();

        // Before this release the shipped default was 'default' (the Text links layout),
        // and admin_apply_default_settings() wrote it into config on every existing site.
        set_config('sectiontype', 'default', 'format_designer');

        $this->run_upgrade_from(self::VERSION_PLAIN_DEFAULT - 1);

        $this->assertSame(
            'default',
            get_config('format_designer', 'sectiontype'),
            'The upgrade must not change a section layout the site already had'
        );
    }

    /**
     * An explicitly chosen non-default layout is equally untouchable.
     */
    public function test_explicitly_chosen_layout_is_preserved(): void {
        $this->resetAfterTest();

        set_config('sectiontype', 'cards', 'format_designer');
        $this->run_upgrade_from(self::VERSION_PLAIN_DEFAULT - 1);

        $this->assertSame('cards', get_config('format_designer', 'sectiontype'));
    }

    /**
     * Only a site with no stored value at all adopts the new "plain" default.
     */
    public function test_site_without_a_stored_layout_adopts_plain(): void {
        $this->resetAfterTest();

        unset_config('sectiontype', 'format_designer');
        $this->run_upgrade_from(self::VERSION_PLAIN_DEFAULT - 1);

        $this->assertSame('plain', get_config('format_designer', 'sectiontype'));
    }

    /**
     * Per-course and per-section stored layouts must never be rewritten by the upgrade,
     * whatever the global default does.
     */
    public function test_stored_section_layouts_are_untouched(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'designer', 'numsections' => 3],
            ['createsections' => true]
        );
        $format = course_get_format($course);
        $modinfo = get_fast_modinfo($course);

        $expected = [];
        foreach ([1, 2, 3] as $num) {
            $sectioninfo = $modinfo->get_section_info($num);
            $type = ($num === 1) ? 'cards' : (($num === 2) ? 'list' : 'circles');
            $format->update_section_format_options(['id' => $sectioninfo->id, 'sectiontype' => $type]);
            $expected[$sectioninfo->id] = $type;
        }

        unset_config('sectiontype', 'format_designer');
        $this->run_upgrade_from(self::VERSION_PLAIN_DEFAULT - 1);

        foreach ($expected as $sectionid => $type) {
            $this->assertSame($type, $DB->get_field('course_format_options', 'value', [
                'courseid' => $course->id, 'format' => 'designer',
                'sectionid' => $sectionid, 'name' => 'sectiontype',
            ]), 'A stored per-section layout must survive the upgrade');
        }
    }

    /**
     * The old single "sectionlayouts" toggle was split in two. An administrator who had
     * turned it off must find both replacements off, not silently re-enabled.
     */
    public function test_disabled_sectionlayouts_toggle_carries_over(): void {
        $this->resetAfterTest();

        set_config('feature_sectionlayouts', 0, 'format_designer');
        $this->run_upgrade_from(self::VERSION_FEATURE_SPLIT - 1);

        $this->assertSame('0', get_config('format_designer', 'feature_sectionactivitylayout'));
        $this->assertSame('0', get_config('format_designer', 'feature_coursesectionlayout'));
        $this->assertFalse(get_config('format_designer', 'feature_sectionlayouts'), 'The old key must be removed');
        $this->assertFalse(helper::feature_enabled('sectionactivitylayout'));
        $this->assertFalse(helper::feature_enabled('coursesectionlayout'));
    }

    /**
     * An enabled old toggle carries over as enabled.
     */
    public function test_enabled_sectionlayouts_toggle_carries_over(): void {
        $this->resetAfterTest();

        set_config('feature_sectionlayouts', 1, 'format_designer');
        $this->run_upgrade_from(self::VERSION_FEATURE_SPLIT - 1);

        $this->assertSame('1', get_config('format_designer', 'feature_sectionactivitylayout'));
        $this->assertSame('1', get_config('format_designer', 'feature_coursesectionlayout'));
    }

    /**
     * A site that never saw the old toggle gets no new keys written, and both features
     * stay enabled by virtue of the unset-means-on rule.
     */
    public function test_site_without_the_old_toggle_is_left_alone(): void {
        $this->resetAfterTest();

        unset_config('feature_sectionlayouts', 'format_designer');
        unset_config('feature_sectionactivitylayout', 'format_designer');
        unset_config('feature_coursesectionlayout', 'format_designer');

        $this->run_upgrade_from(self::VERSION_FEATURE_SPLIT - 1);

        $this->assertFalse(get_config('format_designer', 'feature_sectionactivitylayout'));
        $this->assertFalse(get_config('format_designer', 'feature_coursesectionlayout'));
        $this->assertTrue(helper::feature_enabled('sectionactivitylayout'));
        $this->assertTrue(helper::feature_enabled('coursesectionlayout'));
    }
}
