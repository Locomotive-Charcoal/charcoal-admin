/**
* Attachment widget
* You can associate a perticular object to another
* using this widget.
*
* @see widget.js (Charcoal.Admin.Widget
*/
Charcoal.Admin.Widget_Attachment = function ()
{
    return this;
};

Charcoal.Admin.Widget_Attachment.prototype = Object.create(Charcoal.Admin.Widget.prototype);
Charcoal.Admin.Widget_Attachment.prototype.constructor = Charcoal.Admin.Widget_Attachment;
Charcoal.Admin.Widget_Attachment.prototype.parent = Charcoal.Admin.Widget.prototype;

/**
 * Called upon creation
 * Use as constructor
 * Access available configurations with `this.opts()`
 * Encapsulate all events within the current widget
 * element: `this.element()`.
 *
 *
 * @see Component_Manager.render()
 * @return {thisArg} Chainable
 */
Charcoal.Admin.Widget_Attachment.prototype.init = function ()
{
    // Necessary assets.
    if (typeof $.fn.sortable !== 'function') {
        var that = this;
        this.load_assets(function () {
            that.init();
        });
        return this;
    }
    // var config = this.opts();
    this.element().find('.js-attachment-sortable').find('.js-grid-container').sortable({
        connectWith: '.js-grid-container'
    }).disableSelection();

    this.listeners();
    return this;
};

Charcoal.Admin.Widget_Attachment.prototype.load_assets = function (cb)
{
    $.getScript('https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js',
    function () {
        if (typeof cb === 'function') {
            cb();
        }
    });
    return this;
};

/**
 * Bind listeners
 *
 * @return {thisArg} Chainable
 */
Charcoal.Admin.Widget_Attachment.prototype.listeners = function ()
{
    // Scope
    var that = this;

    // Jquery element
    // var el = this.element();

    // Prevent multiple binds
    this.element().off('click');

    this.element().on('click', '.js-add-attachment', function (e)
    {
        e.preventDefault();
        var type = $(this).data('type');
        if (!type) {
            return false;
        }
        var title = $(this).data('title') || 'Content Element';
        that.create_attachment(type, title);
    });
};

/**
 * Select an attachment from the list
 *
 * @param  {jQuery Object} elem Clicked element
 * @return {thisArg}            (Chainable)
 */
Charcoal.Admin.Widget_Attachment.prototype.select_attachment = function (elem)
{
    if (!elem.data('id') || !elem.data('type')) {
        // Invalid
        return this;
    }
};

Charcoal.Admin.Widget_Attachment.prototype.create_attachment = function (type, title)
{
    // Scope
    var that = this;

    var data = {
        title: title,
        widget_type: 'charcoal/admin/widget/quickForm',
        form_ident: 'quick',
        widget_options: {
            obj_type: type,
            obj_id: 0
        }
    };
    this.dialog(data, function (response) {
        if (response.success) {
            // Call the quickForm widget js.
            // Really not a good place to do that.
            if (!response.widget_id) {
                return false;
            }

            Charcoal.Admin.manager().add_widget({
                id: response.widget_id,
                type: 'charcoal/admin/widget/quick-form',
                data: {
                    obj_type: type,
                    obj_id: 0
                },
                save_callback: function (response) {
                    if (response.success) {
                        BootstrapDialog.closeAll();
                        that.reload();
                    }
                }
            });
            // Re render.
            // This is not good.
            Charcoal.Admin.manager().render();
        }
    });
};

/**
 * [save description]
 * @return {[type]} [description]
 */
Charcoal.Admin.Widget_Attachment.prototype.save = function ()
{
    // Scope
    var that = this;

    var opts = that.opts();
    var data = {
        obj_type: opts.data.obj_type,
        obj_id: opts.data.obj_id,
        attachments: []
    };

    this.element().find('.js-attachment-container').find('.js-attachment').each(function (i)
    {
        var $this = $(this);
        var id = $this.data('id');
        var type = $this.data('type');

        data.attachments.push({
            attachment_id: id,
            attachment_type: type, // Further use.
            position: i
        });
    });

    $.post('join', data, function () {});

};

/**
 * Widget options as output by the widget itself.
 * @return {[type]} [description]
 */
Charcoal.Admin.Widget_Attachment.prototype.widget_options = function ()
{
    return this.opts('widget_options');
};
