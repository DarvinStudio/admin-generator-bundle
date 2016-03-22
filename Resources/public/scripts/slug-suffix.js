$(document).ready(function () {
    var getWidget = function ($child) {
        return $child.parents('.slug_suffix').first();
    };

    var buildUrlPrefix = function ($widget) {
        var parentSlug = $widget.parents('form').first().find($widget.data('parent-select')).children('option:selected')
            .data($widget.data('parent-option-data-slug'));

        return $widget.data('url-prefix') + ('undefined' !== typeof parentSlug ? parentSlug + '/' : '');
    };

    var buildUrl = function ($widget) {
        var slugSuffix = $widget.find('.form_widget input').val();

        return buildUrlPrefix($widget) + (slugSuffix ? slugSuffix : '___') + $widget.data('url-suffix');
    };

    var updateWidget = function ($widget) {
        var $input = $widget.find('.form_widget input');
        var $reset = $widget.find('.reset');
        var slugSuffix = $input.val();
        $input.data('default').toString() !== slugSuffix ? $reset.show() : $reset.hide();

        $widget.find('.url_prefix').text(buildUrlPrefix($widget));

        var url = buildUrl($widget);
        $widget.find('.link_widget a').attr('href', url).text(url);

        if ($widget.data('default-url').toString() !== url) {
            $widget.addClass('changed');

            return;
        }
        if (slugSuffix) {
            $widget.removeClass('changed');
        }
    };

    $('.slug_suffix').each(function () {
        var $widget = $(this);

        updateWidget($widget);

        $widget.find('.form_widget input').counter({
            count:  'up',
            goal:   'sky',
            target: $widget.find('.form_widget .input_value')
        });
        $widget.parents('form').first().on('change', $widget.data('parent-select'), function () {
            updateWidget($widget);
        });
    });

    $('body')
        .on('click', '.slug_suffix .edit', function () {
            var $widget = getWidget($(this));

            $widget.find('.form_widget').show();
            $widget.find('.link_widget').hide();
        })
        .on('click', '.slug_suffix .reset', function () {
            var $widget = getWidget($(this));

            var $input = $widget.find('.form_widget input');
            $input.val($input.data('default'));

            $widget.find('.update').trigger('click');
        })
        .on('click', '.slug_suffix .update', function () {
            var $widget = getWidget($(this));

            updateWidget($widget);

            $widget.find('.form_widget').hide();
            $widget.find('.link_widget').show();
        })
        .on('click', '.slug_suffix.changed .link_widget a', function (e) {
            e.preventDefault();
        });
});
