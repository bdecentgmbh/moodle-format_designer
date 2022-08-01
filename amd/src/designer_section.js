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
 define(['jquery', 'core/fragment', 'core/templates', 'core/loadingicon', 'core/ajax', 'core_course/actions'],
 function($, Fragment, Templates, Loadingicon, Ajax, Actions) {

    var SELECTOR = {
        ACTIVITYLI: 'li.activity',
        SECTIONLI: 'li.section',
        ACTIVITYACTION: 'a.cm-edit-action',
        SECTIONACTIONMENU: '.section_action_menu.designer-menu',
    };

    Y.use('moodle-course-coursebase', function() {
        var courseformatselector = M.course.format.get_section_selector();
        if (courseformatselector) {
            SELECTOR.SECTIONLI = courseformatselector;
        }
    });


    /**
     * Control designer format action
     * @param {int} courseId
     * @param {int} contextId
     * @param {array} popupActivities
     */
    let DesignerSection = function(courseId, contextId, popupActivities) {
        var self = this;
        self.courseId = courseId;
        self.contextId = contextId;
        self.popupActivities = popupActivities;

        $('body').delegate(self.SectionController, 'click', self.sectionLayoutaction.bind(this));
        $("body").delegate(self.RestrictInfo, "click", self.moduleHandler.bind(this));
        $("body").delegate(self.sectionRestricted, "click", this.sectionRestrictHandler.bind(this));
        $('body').delegate(self.fullDescription, "click", self.fullmodcontentHandler.bind(this));
        $('body').delegate(self.trimDescription, "click", self.trimmodcontentHandler.bind(this));
        $('body').delegate(self.goToURL, "click", self.redirectToModule.bind(this));
        window.onhashchange = function() {
            self.expandSection();
        };
        this.expandSection();

        if ($('.course-type-flow').length > 0) {
            $('.collapse').on('show.bs.collapse', function() {
                $(this).parents('li.section').addClass('stack-header-collapsing');
                var sectionid = $(this).parents('li.section').attr('id');
                var section = document.getElementById(sectionid);
                var distance = section.offsetTop - document.body.scrollTop;
                setTimeout(() => window.scroll(0, distance), 50);
            }).on('shown.bs.collapse', function() {
                $(this).parents('li.section').removeClass('stack-header-collapsing');
            });
        }

    };

    /**
     * Selector section controller.
     */
    DesignerSection.prototype.goToURL = '.designer [data-action="go-to-url"]';

    DesignerSection.prototype.SectionController = ".designer #section-designer-action .dropdown-menu a";

    DesignerSection.prototype.RestrictInfo = ".designer .designer-section-content .call-action-block";

    DesignerSection.prototype.moduleBlock = ".designer .designer-section-content li.activity";

    DesignerSection.prototype.loadingElement = ".icon-loader-block";

    DesignerSection.prototype.sectionRestricted = ".designer .availability-section-block .section-restricted-action";

    DesignerSection.prototype.fullDescription = ".designer-section-content li .fullcontent-summary .mod-description-action";

    DesignerSection.prototype.trimDescription = ".designer-section-content li .trim-summary .mod-description-action";

    DesignerSection.prototype.modules = null;

    DesignerSection.prototype.redirectToModule = function(event) {
        let nodeName = event.target.nodeName;
        let preventionNodes = ['a', 'button', 'form'];
        let iscircle = event.target.closest('li.activity').classList.contains('circle-layout');
        let isDescription = event.target.classList.contains('mod-description-action');
        let isPadlock = event.target.classList.contains('fa-lock');
        let ispopupModule = event.target.closest('li.activity').classList.contains('popmodule');
        if ((nodeName in preventionNodes)
            || document.body.classList.contains('editing') || iscircle || isDescription || isPadlock || ispopupModule) {
            if (ispopupModule && !document.body.classList.contains('editing')) {
                var li = event.target.closest('li.activity');
                li.querySelector('a[href]').click();
                // event.target.closest('a').click();
            }
            return null;
        }
        var card = event.target.closest("[data-action=go-to-url]");
        let modurl = card.getAttribute('data-url');
        window.location.href = modurl;
        return true;
    };

    DesignerSection.prototype.expandSection = () => {
        var sectionID = window.location.hash;
        if (sectionID) {
            var id = sectionID.substring(1);
            var section = document.getElementById(id);
            if (section) {
                var title = section.querySelector('.section-header-content');
                if (title) {
                    title.classList.remove('collapsed');
                    title.setAttribute('aria-expanded', true);
                }
                var content = section.querySelector('.content');
                if (content) {
                    content.classList.add('show');
                }
                if (document.getElementById('section-course-accordion') !== null) {
                    document.getElementById('section-head-0').classList.add('collapsed');
                    document.getElementById('section-content-0').classList.remove('show');
                }
                section.scrollIntoView();
            }
        }
    };

    DesignerSection.prototype.fullmodcontentHandler = function(event) {
        var THIS = $(event.currentTarget);
        let fullContent = $(THIS).closest('li.activity').find('.fullcontent-summary');
        let trimcontent = $(THIS).closest('li.activity').find('.trim-summary');
        if (trimcontent.hasClass('summary-hide')) {
            trimcontent.removeClass('summary-hide');
            fullContent.addClass('summary-hide');
        }
    };

    DesignerSection.prototype.trimmodcontentHandler = function(event) {
        var THIS = $(event.currentTarget);
        let fullContent = $(THIS).closest('li.activity').find('.fullcontent-summary');
        let trimcontent = $(THIS).closest('li.activity').find('.trim-summary');
        if (fullContent.hasClass('summary-hide')) {
            fullContent.removeClass('summary-hide');
            trimcontent.addClass('summary-hide');
        }
    };

    DesignerSection.prototype.sectionRestrictHandler = function(event) {
        var sectionRestrictInfo = $(event.currentTarget).prev();
        if (sectionRestrictInfo) {
            if (!sectionRestrictInfo.hasClass('show')) {
                sectionRestrictInfo.addClass('show');
            } else {
                sectionRestrictInfo.removeClass('show');
            }
        }
    };

    DesignerSection.prototype.moduleHandler = function(event) {
        event.preventDefault();
        var restrictBlock = $(event.currentTarget).parents('.restrict-block');
        if (restrictBlock.length) {
            if (!restrictBlock.hasClass('show')) {
                restrictBlock.addClass('show');
            } else {
                restrictBlock.removeClass('show');
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
        var layout = $(event.currentTarget).data('value');
        var layouttext = $(event.currentTarget).text();
        $(event.target).parents(".dropdown").find(".btn").html(layouttext);
        $(event.target).parents(".dropdown").find(".btn").val(layout);
        $(event.target).parent().find("a.dropdown-item").each(function() {
            $(this).removeClass('active');
        });
        $(event.target).addClass('active');
        let dataid = event.target.closest('li.section').getAttribute('data-id');
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
    };

    return {
        init: function(courseId, contextId, popupActivities) {
            return new DesignerSection(courseId, contextId, popupActivities);
        }
    };
});