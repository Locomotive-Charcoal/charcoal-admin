/**
 * Table widget used for listing collections of objects
 * charcoal/admin/widget/table
 *
 * Require:
 * - jQuery
 * - Boostrap3-Dialog
 *
 * @param  {Object}  opts Options for widget
 */

Charcoal.Admin.Widget_Table = function (opts) {
    Charcoal.Admin.Widget.call(this, opts);

    // Widget_Table properties
    this.obj_type       = null;
    this.widget_id      = null;
    this.table_selector = null;
    this.search_query   = null;
    this.filters        = {};
    this.orders         = {};
    this.pagination     = {
        page: 1,
        num_per_page: 50
    };
    this.list_actions = {};
    this.object_actions = {};

    this.template = this.properties = this.properties_options = undefined;

    this.sortable = null;
};

Charcoal.Admin.Widget_Table.prototype = Object.create(Charcoal.Admin.Widget.prototype);
Charcoal.Admin.Widget_Table.prototype.constructor = Charcoal.Admin.Widget_Table;
Charcoal.Admin.Widget_Table.prototype.parent = Charcoal.Admin.Widget.prototype;

/**
 * Necessary for a widget.
 */
Charcoal.Admin.Widget_Table.prototype.init = function () {
    this.set_properties().bind_events();
};

Charcoal.Admin.Widget_Table.prototype.set_properties = function () {
    var opts = this.opts();

    this.obj_type           = opts.data.obj_type           || this.obj_type;
    this.widget_id          = opts.id                      || this.widget_id;
    this.table_selector     = '#' + this.widget_id;
    this.template           = opts.data.template           || this.template;
    this.collection_ident   = opts.data.collection_ident   || 'default'; // @todo remove the hardcoded shit

    if (('properties' in opts.data) && Array.isArray(opts.data.properties)) {
        this.properties = opts.data.properties;
    }

    if (('properties_options' in opts.data) && $.isPlainObject(opts.data.properties_options)) {
        this.properties_options = opts.data.properties_options;
    }

    if (('filters' in opts.data) && Array.isArray(opts.data.filters)) {
        this.filters = opts.data.filters;
    }

    if (('orders' in opts.data) && Array.isArray(opts.data.orders)) {
        this.orders = opts.data.orders;
    }

    if (('pagination' in opts.data) && $.isPlainObject(opts.data.pagination)) {
        this.pagination = opts.data.pagination;
    }

    if (('list_actions' in opts.data) && Array.isArray(opts.data.list_actions)) {
        this.list_actions = opts.data.list_actions;
    }

    if (('object_actions' in opts.data) && Array.isArray(opts.data.object_actions)) {
        this.object_actions = opts.data.object_actions;
    }

    return this;
};

Charcoal.Admin.Widget_Table.prototype.bind_events = function () {
    if (this.sortable !== null) {
        this.sortable.destroy();
    }

    var that = this;

    var $sortable_table = $('tbody.js-sortable', that.table_selector);
    if ($sortable_table.length > 0) {
        this.sortable = new window.Sortable.default($sortable_table.get(), {
            delay: 150,
            draggable: '.js-table-row',
            handle: '.js-sortable-handle',
            mirror: {
                constrainDimensions: true,
            }
        }).on('mirror:create', function (event) {
            var originalCells = event.originalSource.querySelectorAll(':scope > td');
            var mirrorCells = event.source.querySelectorAll(':scope > td');
            originalCells.forEach(function (cell, index) {
                mirrorCells[index].style.width = cell.offsetWidth + 'px';
            });
        }).on('sortable:stop', function (event) {
            if (event.oldIndex !== event.newIndex) {
                var rows = Array.from(event.newContainer.querySelectorAll(':scope > tr')).map(function (row) {
                    if (row.classList.contains('draggable-mirror') || row.classList.contains('draggable--original')) {
                        return '';
                    } else {
                        return row.getAttribute('data-id');
                    }
                }).filter(function (row) {
                    return row !== '';
                });

                $.ajax({
                    method: 'POST',
                    url: Charcoal.Admin.admin_url() + 'object/reorder',
                    data: {
                        obj_type: that.obj_type,
                        obj_orders: rows,
                        starting_order: 1
                    },
                    dataType: 'json'
                }).done(function (response) {
                    console.debug(response);
                    if (response.feedbacks) {
                        Charcoal.Admin.feedback(response.feedbacks).dispatch();
                    }
                });
            }
        });
    }

    $('.js-jump-page-form', that.table_selector).on('submit', function (event) {
        event.preventDefault();

        var $this = $(this);
        var page_num = parseInt($this.find('input').val());

        if (page_num) {
            that.pagination.page = page_num;
            that.reload();
        }
    });

    $('.js-page-switch', that.table_selector).on('click', function (event) {
        event.preventDefault();

        var $this = $(this);
        var page_num = $this.data('page-num');
        that.pagination.page = page_num;
        that.reload();
    });
};

/**
 * As it says, it ADDs a filter to the already existing list
 * @param object
 * @return this chainable
 * @see set_filters
 */
Charcoal.Admin.Widget_Table.prototype.add_filter = function (filter) {
    var filters = this.get_filters();

    // Null by default
    // When you add a filter, you want it to be
    // in an object
    if (filters === null) {
        filters = {};
    }

    filters = $.extend(filters, filter);
    this.set_filters(filters);

    return this;
};

/**
 * This will overwrite existing filters
 */
Charcoal.Admin.Widget_Table.prototype.set_filters = function (filters) {
    this.filters = filters;
};

/**
 * Getter
 * @return {Object | null} filters
 */
Charcoal.Admin.Widget_Table.prototype.get_filters = function () {
    return this.filters;
};

/**
 * Set the user search query
 *
 * @param  {string|null} query
 * @return {void}
 */
Charcoal.Admin.Widget_Table.prototype.set_search_query = function (query) {
    this.search_query = query;
};

/**
 * Get the user search query
 *
 * @return {string|null}
 */
Charcoal.Admin.Widget_Table.prototype.get_search_query = function () {
    return this.search_query;
};

Charcoal.Admin.Widget_Table.prototype.widget_options = function () {
    return {
        obj_type:          this.obj_type,
        template:          this.template,
        collection_ident:  this.collection_ident,
        collection_config: {
            properties:         this.properties,
            properties_options: this.properties_options,
            search_query:       this.search_query,
            filters:            this.filters,
            orders:             this.orders,
            pagination:         this.pagination,
            list_actions:       this.list_actions,
            object_actions:     this.object_actions
        }
    };
};
