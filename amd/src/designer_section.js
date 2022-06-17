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
 define(['jquery', 'core/fragment', 'core/templates', 'core/loadingicon', 'core/ajax'],
 function($, Fragment, Templates, Loadingicon, Ajax) {

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
     * @param {bool} popupActivities
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
        $('body').on('click keypress', SELECTOR.ACTIVITYLI + ' ' +
        SELECTOR.ACTIVITYACTION + '[data-action]', this.editModuleRenderer.bind(this));
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

    DesignerSection.prototype.moduleDescription = ".designer .designer-section-content li .mod-description-action";

    DesignerSection.prototype.fullDescription = ".designer-section-content li .fullcontent-summary .mod-description-action";

    DesignerSection.prototype.trimDescription = ".designer-section-content li .trim-summary .mod-description-action";

    DesignerSection.prototype.modules = null;

    DesignerSection.prototype.addSectionSpinner = function(sectioninfo) {
        var sectionelement = $(sectioninfo).addClass('editinprogress');
        var actionarea = sectionelement.find('li.section').get(0);
        if (actionarea) {
            var spinner = M.util.add_spinner(Y, Y.Node(actionarea));
            spinner.show();
            return spinner;
        }
        return null;
    };


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
                // Event.target.closest('a').click();
            }
            return null;
        }
        var card = event.target.closest("[data-action=go-to-url]");
        let modurl = card.getAttribute('data-url');
        window.location.href = modurl;
        return true;
    };

    DesignerSection.prototype.expandSection = () => {
        // Alert();
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

    DesignerSection.prototype.removeSectionSpinner = function(sectioninfo, spinner, delay) {
        var element = $(sectioninfo);
        window.setTimeout(function() {
            element.removeClass('editinprogress');
            if (spinner) {
                spinner.hide();
            }
        }, delay);
    };

    /**
     * Wrapper for Y.Moodle.core_course.util.cm.getId
     *
     * @param {JQuery} element
     * @returns {Integer}
     */
     DesignerSection.prototype.getModuleId = function(element) {
        var id;
        Y.use('moodle-course-util', function(Y) {
            id = Y.Moodle.core_course.util.cm.getId(Y.Node(element.get(0)));
        });
        return id;
    };

    DesignerSection.prototype.refreshSectionInfo = function(target) {
        var self = this;
        let sectioninfo = target.currentTarget.closest("li.section");
        let sectionId = $(sectioninfo).attr('data-id');
        let sectionReturn = $(sectioninfo).attr('data-sectionreturnid');
        var spinner = self.addSectionSpinner(sectioninfo);
        var args = {
            courseid: self.courseId,
            sectionid: sectionId,
            sectionreturn: sectionReturn
        };
        window.setTimeout(function(args) {
            var promises = Ajax.call([{
                methodname: 'format_designer_section_refresh',
                args: args
            }], true);
            $.when.apply($, promises)
                .done(function(dataencoded) {
                    var data = $.parseJSON(dataencoded);
                    if (data.modules !== undefined) {
                        for (var i in data.modules) {
                            self.replaceActivityhtml(data.modules[i]);
                        }
                    }
                });

        }, 1000, args);
        self.removeSectionSpinner(sectioninfo, spinner, 400);
    };

    DesignerSection.prototype.editModuleRenderer = function(event) {
        var self = this;
        if (event.type === 'keypress' && event.keyCode !== 13) {
            return;
        }
        var actionItem = $(event.currentTarget),
        moduleElement = actionItem.closest(SELECTOR.ACTIVITYLI),
        action = actionItem.attr('data-action'),
        moduleId = self.getModuleId(moduleElement);
        switch (action) {
            case 'moveleft':
            case 'moveright':
            case 'delete':
            case 'duplicate':
            case 'hide':
            case 'stealth':
            case 'show':
            case 'groupsseparate':
            case 'groupsvisible':
            case 'groupsnone':
                break;
            default:
                // Nothing to do here!
                return;
        }
        if (!moduleId) {
            return;
        }
        event.preventDefault();
        if (action === 'delete') {
            // Deleting requires confirmation.
            window.setTimeout(function() {
                $('.modal-footer button[data-action="save"]').click(function() {
                    self.refreshSectionInfo(event);
                });
            }, 500);
        } else {
            self.refreshSectionInfo(event);
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
        var ULClasses = {
            'cards': 'card-deck card-layout',
            'list': 'list-layout',
            'default': 'link-layout',
            'circles': 'circles-layout',
            'horizontal_circles': 'circles-layout horizontal-circles-layout'

        };
        var promises = Ajax.call([{
                methodname: 'format_designer_set_section_options',
                args: args
            }], true);
        $.when.apply($, promises)
            .done(function(dataencoded) {
                var data = $.parseJSON(dataencoded);
                if (data.modules !== undefined) {
                    for (var i in data.modules) {
                        self.replaceActivityhtml(data.modules[i]);
                    }
                    document.getElementById(sectionId).classList.forEach((className) => {
                        if (className.startsWith('section-type-')) {
                            document.getElementById(sectionId).classList.remove(className);
                        }
                    });
                    document.getElementById(sectionId).classList.add('section-type-' + layout);
                    $('#' + sectionId).find('ul.section').removeClass(ULClasses.cards);
                    $('#' + sectionId).find('ul.section').removeClass(ULClasses.list);
                    $('#' + sectionId).find('ul.section').removeClass(ULClasses.default);
                    $('#' + sectionId).find('ul.section').removeClass(ULClasses.circles);
                    $('#' + sectionId).find('ul.section').addClass(ULClasses[layout]);
                }
            });
        Loadingicon.addIconToContainerRemoveOnCompletion(iconBlock, promises);
    };

    /**
     * Replaces the course module with the new html (used to update module after it was edited or its visibility was changed).
     *
     * @param {String} activityHTML
     */
    DesignerSection.prototype.replaceActivityhtml = function(activityHTML) {
        $('<div>' + activityHTML + '</div>').find(SELECTOR.ACTIVITYLI).each(function() {
            // Extract id from the new activity html.
            var id = $(this).attr('id');
            // Find the existing element with the same id and replace its contents with new html.
            $(SELECTOR.ACTIVITYLI + '#' + id).replaceWith(activityHTML);
            // Initialise action menu.
            initActionMenu(id);
        });
    };

    /**
     * Initialise action menu for the element (section or module)
     *
     * @param {String} elementid CSS id attribute of the element
     */
    var initActionMenu = function(elementid) {
        // Initialise action menu in the new activity.
        Y.use('moodle-course-coursebase', function() {
            M.course.coursebase.invoke_function('setup_for_resource', '#' + elementid);
        });
        if (M.core.actionmenu && M.core.actionmenu.newDOMNode) {
            M.core.actionmenu.newDOMNode(Y.one('#' + elementid));
        }
    };

    return {
        init: function(courseId, contextId, popupActivities) {
            return new DesignerSection(courseId, contextId, popupActivities);
        }
    };
});