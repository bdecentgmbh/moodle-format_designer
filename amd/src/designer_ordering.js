define(['jquery', 'core_course/actions', 'core/ajax'], function($, action, ajax) {

    var CSS = {
        EDITINPROGRESS: 'editinprogress',
        EDITINGMOVE: 'editing_move'
    };

    var SELECTOR = {
        ACTIVITYLI: 'li.activity',
        ACTIONAREA: '.actions',
        SECTIONLI: 'li.section',
    };
    /**
     * Implement the init.
     */
    function init() {

        // Register a function to be executed after D&D of an activity.
        Y.use('moodle-course-coursebase', function() {

                M.course.coursebase.register_module({
                    // Ignore camelcase eslint rule for the next line because it is an expected name of the callback.
                    // eslint-disable-next-line camelcase
                    set_visibility_resource_ui: function(args) {
                        var mainelement = $(args.element.getDOMNode());
                        var cmid = getModuleId(mainelement);
                        if (cmid) {
                            mainelement.css('opacity', 0);
                            var sectionreturn = mainelement.find('.' + CSS.EDITINGMOVE).attr('data-sectionreturn');
                            var sectionId = mainelement.closest(SELECTOR.SECTIONLI).attr('data-id');
                            var spinner = addActivitySpinner(mainelement);
                            var promises = ajax.call([{
                                methodname: 'format_designer_get_module',
                                args: {id: cmid, sectionid: sectionId, sectionreturn: sectionreturn}
                            }], true);
                            $.when.apply($, promises)
                                .done(function(data) {
                                removeSpinner(mainelement, spinner, 400);
                                    replaceActivityHtmlWith(data);
                                }).fail(function() {
                                    removeSpinner(mainelement, spinner);
                                });
                        }
                    }

            });

        });
    }

    /**
     * Replaces the course module with the new html (used to update module after it was edited or its visibility was changed).
     *
     * @param {String} activityHTML
     */
    function replaceActivityHtmlWith(activityHTML) {
        setTimeout(function() {
            $('<div>' + activityHTML + '</div>').find(SELECTOR.ACTIVITYLI).each(function() {
                // Extract id from the new activity html.
                var id = $(this).attr('id');
                // Find the existing element with the same id and replace its contents with new html.
                $(SELECTOR.ACTIVITYLI + '#' + id).replaceWith(activityHTML);
                // Initialise action menu.
                initActionMenu(id);
                $(SELECTOR.ACTIVITYLI + '#' + id).css('opacity', 1);
            });
        }, 1000);
    }

    /**
     * Wrapper for Y.Moodle.core_course.util.cm.getId
     *
     * @param {JQuery} element
     * @returns {Integer}
     */
    function getModuleId(element) {
        var id;
        Y.use('moodle-course-util', function(Y) {
            id = Y.Moodle.core_course.util.cm.getId(Y.Node(element.get(0)));
        });
        return id;
    }

    /**
     * Removes the spinner element
     *
     * @param {JQuery} element
     * @param {Node} spinner
     * @param {Number} delay
     */
    function removeSpinner(element, spinner, delay) {
        window.setTimeout(function() {
            element.removeClass(CSS.EDITINPROGRESS);
            if (spinner) {
                spinner.hide();
            }
        }, delay);
    }

    /**
     * Initialise action menu for the element (section or module)
     *
     * @param {String} elementid CSS id attribute of the element
     */
    function initActionMenu(elementid) {
        // Initialise action menu in the new activity.
        Y.use('moodle-course-coursebase', function() {
            M.course.coursebase.invoke_function('setup_for_resource', '#' + elementid);
        });
        if (M.core.actionmenu && M.core.actionmenu.newDOMNode) {
            M.core.actionmenu.newDOMNode(Y.one('#' + elementid));
        }
    }

    /**
     * Wrapper for M.util.add_spinner for an activity
     *
     * @param {JQuery} activity
     * @returns {Node}
     */
    function addActivitySpinner(activity) {
        activity.addClass(CSS.EDITINPROGRESS);
        var actionarea = activity.find(SELECTOR.ACTIONAREA).get(0);
        if (actionarea) {
            var spinner = M.util.add_spinner(Y, Y.Node(actionarea));
            spinner.show();
            return spinner;
        }
        return null;
    }

    return {
        init: init
    };
});