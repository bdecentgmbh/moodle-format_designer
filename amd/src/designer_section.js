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
 define(['jquery', 'core/fragment', 'core/templates', 'core/loadingicon'],
 function($, Fragment, Templates, Loadingicon) {
    /**
     * Control designer format action
     * @param {int} courseId
     * @param {int} contextId
     */
    let DesignerSection = function(courseId, contextId) {
        var self = this;
        self.courseId = courseId;
        self.contextId = contextId;
        var designerSelectorSections = document.querySelectorAll(this.SectionController + " .dropdown-menu a");
         if (designerSelectorSections) {
            designerSelectorSections.forEach(function(item) {
                item.addEventListener('click', self.sectionLayoutaction.bind(self));
            });
        }
        $(self.moduleBlock).on("click", "li .card-body", this.moduleHandler.bind(this));
    };

    DesignerSection.prototype.moduleHandler = function(event) {
        event.preventDefault();
        if ($(event.target).parents(".call-action-block").length == 0) {
            var modUrl = $(event.currentTarget).closest('.card').attr('data-url');
            if (modUrl) {
                window.open(modUrl, '_self');
            }
        } else {
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

    /**
     * Implementaion swith the section layout.
     * @param {object} event
     */
    DesignerSection.prototype.sectionLayoutaction = function(event) {
        var self = this;
        let sectionId = event.target.closest('li.section').getAttribute('id');
        var iconBlock = "#" + sectionId + " " + self.loadingElement;
        Loadingicon.addIconToContainerWithPromise(iconBlock);
        var layout = $(event.currentTarget).data('value');
        var layouttext = $(event.currentTarget).text();
        $(event.target).parents(".dropdown").find(".btn").html(layouttext);
        $(event.target).parents(".dropdown").find(".btn").val(layout);
        $(event.target).parent().find("a.dropdown-item").each(function() {
            $(this).removeClass('active');
        });
        $(event.target).addClass('active');
        let sectionnumber = event.target.closest('li.section').getAttribute('data-sectionid');
        var args = {
            courseid: self.courseId,
            sectionnumber: sectionnumber,
            sectioncol: $(event.currentTarget).data('option'),
            sectioncolvalue: layout
        };
        Fragment.loadFragment('format_designer', 'get_section_modules', self.contextId, args).done((html, js) => {
            if (html) {
                var updateId = `.course-content .designer #section-${sectionnumber} #designer-section-content`;
                Templates.replaceNode(updateId, html, js);
                $(iconBlock).empty();
                $(self.moduleBlock).on("click", "li .card-body", self.moduleHandler.bind(this));
            }
        });
    };

    /**
     * Selector section controller.
     */
    DesignerSection.prototype.SectionController = ".designer #section-designer-action";

    DesignerSection.prototype.RestrictInfo = ".designer #designer-section-content .call-action-block";

    DesignerSection.prototype.moduleBlock = ".designer #designer-section-content";

    DesignerSection.prototype.loadingElement = ".icon-loader-block";

    return {
        init: function(courseId, contextId) {
            return new DesignerSection(courseId, contextId);
        }
    };
});