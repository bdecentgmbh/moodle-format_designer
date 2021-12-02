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
 * Implemented designer format js.
 *
 * @module     format_designer/designer_section
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 define(['jquery', 'core/ajax', 'core/loadingicon', 'core_courseformat/courseeditor', 'core_course/actions'],
 function($, Ajax, Loadingicon, editor, Actions) {

    /**
     * Control designer format action
     * @param {int} courseId
     * @param {int} contextId
     */
    let DesignerSection = function(courseId, contextId) {
        var self = this;
        self.courseeditor = editor.getCurrentCourseEditor();
        self.courseId = courseId;
        self.contextId = contextId;
        $('body').delegate(self.SectionController, 'click', self.sectionLayoutaction.bind(this));
        $('body').delegate(self.sectionRestricted, "click", this.sectionRestrictHandler.bind(this));
        $('body').delegate(self.moduleBlock, "click", self.moduleHandler.bind(this));
        $('body').delegate(self.moduleDescription, "click", self.modcontentHandler.bind(this));
    };

        /**
         * Selector section controller.
         */
         DesignerSection.prototype.SectionController = ".designer #section-designer-action .dropdown-menu a";

         DesignerSection.prototype.SectionLayoutController = ".designer #section-designer-action .dropdown-menu a";

         DesignerSection.prototype.RestrictInfo = ".designer #designer-section-content .call-action-block";

         DesignerSection.prototype.loadingElement = ".icon-loader-block";

         DesignerSection.prototype.sectionRestricted = ".designer .restricted-section-block .section-restricted-action";

         DesignerSection.prototype.activityli = "li.activity";

         DesignerSection.prototype.ulclasses = {
             'cards': 'card-deck card-layout',
             'list': 'list-layout',
             'default': ''
         };

        DesignerSection.prototype.moduleBlock = ".designer #designer-section-content li.activity";
        DesignerSection.prototype.moduleDescription = ".designer #designer-section-content li .mod-description-action";

    DesignerSection.prototype.sectionRestrictHandler = function(event) {
        var sectionRestrictInfo = $(event.currentTarget).parent();
        if (sectionRestrictInfo) {
            if (!sectionRestrictInfo.hasClass('show')) {
                sectionRestrictInfo.addClass('show');
            } else {
                sectionRestrictInfo.removeClass('show');
            }
        }
    };

    /**
     * Implementaion swith the section layout.
     * @param {object} event
     */
    DesignerSection.prototype.sectionLayoutaction = function(event) {
        var self = this;
        let sectionId = event.target.closest('li.section').getAttribute('id');
        let dataid = event.target.closest('li.section').getAttribute('data-id');
        var layout = $(event.currentTarget).data('value');
        var layouttext = $(event.currentTarget).text();
        $(event.target).parents(".dropdown").find(".btn").html(layouttext);
        $(event.target).parents(".dropdown").find(".btn").val(layout);
        $(event.target).parent().find("a.dropdown-item").each(function() {
            $(this).removeClass('active');
        });
        $(event.target).addClass('active');
        let cms = $("#" + sectionId).find('ul#designer-section-content li').length;
        if (cms) {

            var iconBlock = "#" + sectionId + " " + self.loadingElement;
            var args = {
                courseid: self.courseId,
                sectionid: dataid,
                options: [{name: $(event.currentTarget).data('option'), value: layout}]
            };
            var promises = Ajax.call([{
                methodname: 'format_designer_set_section_options',
                args: args
            }], true);
            $.when.apply($, promises)
            .done(function() {
                const sectionpromise = Actions.refreshSection('#' + sectionId, dataid, 0);
                sectionpromise.then(() => {
                   return '';
                }).catch();
            });
            Loadingicon.addIconToContainerRemoveOnCompletion(iconBlock, promises);
        }
    };


    DesignerSection.prototype.moduleHandler = function(event) {
        if ($(event.target).hasClass('fa-lock')) {
            event.preventDefault();
            var restrictBlock = $(event.currentTarget).find(".restrict-block");
            if (restrictBlock) {
                if (!restrictBlock.hasClass('show')) {
                    restrictBlock.addClass('show');
                } else {
                    restrictBlock.removeClass('show');
                }
            }
        }
    };

    DesignerSection.prototype.modcontentHandler = function(event) {
        var THIS = $(event.currentTarget);
        var fullContent = $(THIS).parent();
        if (fullContent.hasClass('hide')) {
            fullContent.removeClass('hide');
            $(THIS).text("Less");
        } else {
            fullContent.addClass('hide');
            $(THIS).text("More");
        }
    };


    return {
        init: function(courseId, contextId) {
            return new DesignerSection(courseId, contextId);
        }
    };
});