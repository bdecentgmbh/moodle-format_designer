define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {

    let DesignerSection = function(rootSelector, courseId) {
        this.root = $(rootSelector);
        this.sectionnumber = this.root.data('sectionid');
        this.courseId = courseId;

        this.root.on('click', '[data-action=set-section-option]', e => {
            e.preventDefault();
            this.setSectionOptions([{
                name: $(e.currentTarget).data('option'),
                value: $(e.currentTarget).data('value')
            }]).done(() => {
                window.location.href = $(e.currentTarget).attr('href');
                window.location.reload();
            }).fail(Notification.exception);
        });

        this.root.on('click', '[data-action=go-to-url]', e => {
            if ($(e.target).prop('tagName') === 'DIV') {
                window.location.href = $(e.currentTarget).data('url');
            }
        });
    };

    /**
     * Set options for this course section.
     *
     * @param {object} options [{name: 'foo', value: 'Bar'}, ..]
     * @returns {*}
     */
    DesignerSection.prototype.setSectionOptions = function(options) {
        return Ajax.call([{
            methodname: 'format_designer_set_section_options',
            args: {
                courseid: this.courseId,
                sectionnumber: this.sectionnumber,
                options: options
            }
        }])[0];
    };

    return DesignerSection;
});