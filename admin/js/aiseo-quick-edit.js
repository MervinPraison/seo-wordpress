/**
 * Populate Quick Edit fields from list-table data.
 *
 * @package AISEO
 */
(function ($) {
    'use strict';

    if (typeof inlineEditPost === 'undefined') {
        return;
    }

    var originalEdit = inlineEditPost.edit;

    inlineEditPost.edit = function (id) {
        originalEdit.apply(this, arguments);

        var postId = 0;
        if (typeof id === 'object') {
            postId = parseInt(this.getId(id), 10);
        } else {
            postId = parseInt(id, 10);
        }
        if (!postId) {
            return;
        }

        var $row = $('#post-' + postId);
        var $data = $row.find('.aiseo-qe-data').first();
        if (!$data.length) {
            return;
        }

        var $edit = $('#edit-' + postId);
        $edit.find('input[name="aiseo_qe_title"]').val($data.attr('data-title') || '');
        $edit.find('input[name="aiseo_qe_keyword"]').val($data.attr('data-keyword') || '');
        $edit.find('input[name="aiseo_qe_noindex"]').prop('checked', $data.attr('data-noindex') === '1');
    };
})(jQuery);
