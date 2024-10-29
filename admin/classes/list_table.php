<?php

/**
 * List Table class.
 *
 * @package WordPress
 * @subpackage List_Table
 * @since 3.1.0
 * @access private
 */

namespace Ajaxy\LiveSearch\Admin\Classes;

if (!defined('ABSPATH')) exit;

class List_Table extends \WP_List_Table
{

    private $callback_args;
    private $public = true;
    private $setting_prefix = '';
    private $row_class = '';

    function __construct($objects, $public = true, $setting_prefix = '')
    {
        parent::__construct(array(
            'plural' => 'Settings',
            'singular' => 'Setting',
        ));
        $this->items = $objects;
        $this->public = $public;
        $this->setting_prefix = $setting_prefix;
        $this->prepare_items();
    }

    function ajax_user_can()
    {
        return true;
    }

    function prepare_items()
    {
        $search = sanitize_text_field(!empty($_REQUEST['s']) ? trim(stripslashes($_REQUEST['s'])) : '');

        $args = array(
            'search' => $search,
            'page' => $this->get_pagenum(),
            'number' => 10,
        );

        $orderBy = sanitize_text_field(!empty($_REQUEST['orderby']) ? trim(stripslashes($_REQUEST['orderby'])) : '');
        if (!empty($orderBy))
            $args['orderby'] = $orderBy;

        $order = sanitize_text_field(!empty($_REQUEST['order']) ? trim(stripslashes($_REQUEST['order'])) : '');
        if (!empty($order))
            $args['order'] = $order;

        $this->callback_args = $args;

        $this->set_pagination_args(array(
            'total_items' => sizeof($this->items),
            'per_page' => 10,
        ));
    }

    function get_bulk_actions()
    {
        $actions = array();
        $actions['hide'] = __('Hide from results', "ajaxy-instant-search");
        $actions['show'] = __('Show in results', "ajaxy-instant-search");

        return $actions;
    }

    function current_action()
    {
        if (isset($_REQUEST['action']) && ('hide' == sanitize_text_field($_REQUEST['action']) || 'hide' == sanitize_text_field($_REQUEST['action2'])))
            return 'bulk-hide';

        return parent::current_action();
    }

    function get_columns()
    {
        $columns = array(
            'cb'          => '<input type="checkbox" />',
            'title'        => __('Title', "ajaxy-instant-search"),
            'type'    => __('Type', "ajaxy-instant-search"),
            'search_setting' => __('Search setting', "ajaxy-instant-search"),
            'show_on_search' => __('Search', "ajaxy-instant-search"),
            'limit_results' => __('Limit', "ajaxy-instant-search"),
            'order'            => __('Order', "ajaxy-instant-search")
        );

        return $columns;
    }
    function get_column_info()
    {
        if (isset($this->_column_headers))
            return $this->_column_headers;

        $columns = $this->get_columns();
        $hidden = array();

        $this->_column_headers = array($columns, $hidden, $this->get_sortable_columns(), 'cb');

        return $this->_column_headers;
    }

    function get_sortable_columns()
    {
        return array();
    }

    function display_rows_or_placeholder()
    {
        $args = wp_parse_args($this->callback_args, array(
            'page' => 1,
            'number' => 20,
            'search' => '',
            'hide_empty' => 0
        ));

        extract($args, EXTR_SKIP);

        $args['offset'] = ($page - 1) * $number;

        // convert it to table rows
        $out = '';
        $args['order'] = sanitize_text_field(isset($_REQUEST['order']) ? $_REQUEST['order'] : '');
        $args['orderby'] = sanitize_text_field(isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : '');

        if (!empty($this->items)) {
            foreach ($this->items as $object) {
                $this->single_row($object);
            }
        }
        if (empty($this->items)) {
            $out = sprintf('<tr class="no-items"><td class="colspanchange" colspan="%s">%s</td></tr>', esc_attr($this->get_column_count()), esc_html($this->no_items()));
        }

        echo esc_html($out);
    }

    function single_row($field, $level = 0)
    {
        global $AjaxyLiveSearch;
        $setting = (array)$AjaxyLiveSearch->get_setting($this->setting_prefix . $field['name'], $this->public);
        $field['settings'] = $setting;
        $add_class = ($setting['show'] == 1 ? 'row-yes' : 'row-no');
        $this->row_class = ($this->row_class == '' ? 'alternate' : '');

        echo sprintf('<tr id="type-%s" class="%s %s">%s</tr>', esc_attr($field['name']), esc_attr($this->row_class), esc_attr($add_class), esc_html($this->single_row_columns($field)));
    }

    function column_cb($field)
    {
        return sprintf('<input type="checkbox" name="template_id[]" value="%s" />', esc_attr($field['name']));
    }
    function column_show_on_search($field)
    {
        $setting = $field['settings'];
        return sprintf('<span>%s</span>', esc_html(($setting['show'] == 1 ? 'Yes' : 'No')));
    }
    function column_search_setting($field)
    {
        $setting = $field['settings'];
        return ($setting['search_content'] == 0 ? 'Only title' : 'both title and content');
    }
    function column_limit_results($field)
    {
        $setting = $field['settings'];
        return $setting['limit'];
    }
    function column_order($field)
    {
        $setting = $field['settings'];
        return $setting['order'];
    }
    function column_type($field)
    {
        if ($field['type'] == 'taxonomy') {
            $link = admin_url() . "edit-tags.php?taxonomy=" . $field['name'];
        } elseif ($field['type'] == 'post_type') {
            $link = admin_url() . "edit.php?post_type=" . $field['name'];
        } else {
            $link = 'javascript:;';
        }

        return '<a href="' . $link . '">' . $field['name'] . '</a> (' . $field['type'] . ')';
    }
    function column_title($field)
    {
        global $AjaxyLiveSearch;

        $name =  $field['label'];

        $edit_link = menu_page_url('ajaxy_sf_admin', false) . '&type=' . $field['type'] . '&name=' . $field['name'] . '&edit=1';

        /* translators: %s is replaced with the field label */
        $out = '<strong><a class="row-title" href="' . $edit_link . '" title="' . esc_attr(sprintf(__('Edit &#8220;%s&#8221;'), $name)) . '">' . $name . '</a></strong><br />';

        $actions = array();

        $tab = sanitize_text_field(isset($_GET['tab']) ? $_GET['tab'] : '');

        $actions['edit'] = '<a href="' . $edit_link . '">' . esc_html('Edit template & Settings', "ajaxy-instant-search") . '</a>';

        $setting = (array)$AjaxyLiveSearch->get_setting($this->setting_prefix . $field['name'], $this->public);
        if ($setting['show'] == 1) :
            $actions['hide'] = "<a class='hide-field' href='" . wp_nonce_url(menu_page_url('ajaxy_sf_admin', false) . '&amp;name=' . $field['name'] . '&amp;type=' . $field['type'] . '&amp;show=0&amp;tab=' . $tab, 'hide-post_type_' . $field['name']) . "'>" . esc_html('Hide from results', "ajaxy-instant-search") . "</a>";
        else :
            $actions['show'] = "<a class='show-field' href='" . wp_nonce_url(menu_page_url('ajaxy_sf_admin', false) . '&amp;name=' . $field['name'] . '&amp;type=' . $field['type'] . '&amp;show=1&amp;tab=' . $tab, 'show-post_type_' . $field['name']) . "'>" . esc_html('show in results', "ajaxy-instant-search") . "</a>";
        endif;
        $out .= $this->row_actions($actions);
        $out .= '<div class="hidden" id="inline_' . $field['name'] . '">';
        $out .= '<div class="name">' . $field['label'] . '</div>';

        return $out;
    }
    function column_default($field, $column_name)
    {
        return $field[$column_name];
    }
}
