$(document).ready(function () {
    var buttons = {
        add:      '<button class="collection_add" type="button">' + Translator.trans('form_collections.add') + '</button>',
        'delete': '<button class="collection_delete" type="button">' + Translator.trans('form_collections.delete') + '</button>'
    };

    var updateLabels = function ($collection) {
        if ($collection.data('allow-add')) {
            $collection.children().each(function (index) {
                $(this).children('label:first').text(index + 1);
            });
        }
    };

    var init;
    (init = function (context) {
        var $collections = $(context || 'body').find('.collection[data-prototype]:not([data-autoinit="0"])');

        $collections.each(function () {
            var $collection = $(this);

            if ($collection.data('allow-delete')) {
                $collection.children().each(function () {
                    $(this).append(buttons.delete);
                });
            }
            if ($collection.data('allow-add')) {
                updateLabels($collection);

                $collection.append(buttons.add);
            }
        });
    })();
    $(document).on('formCollectionAdd', function (e, $newElement) {
        init($newElement);
    });

    $('body')
        .on('click', 'form .collection[data-prototype] .collection_add', function () {
            var $addButton = $(this);
            var $collection = $addButton.closest('.collection[data-prototype]');
            var newElement = $collection.data('prototype')
                .replace(/__name__label__/g, $collection.data('index') + 1)
                .replace(/__name__/g, $collection.data('index'));
            var $newElement = $(newElement);

            if ($collection.data('allow-delete')) {
                $newElement.append(buttons.delete);
            }

            $addButton.before($newElement);

            updateLabels($collection);

            $(document).trigger('formCollectionAdd', $newElement);

            $collection.data('index', $collection.data('index') + 1);
        })
        .on('click', 'form .collection .collection_delete', function () {
            var $deleteButton = $(this);
            var $collection = $deleteButton.closest('.collection[data-prototype]');

            $deleteButton.closest('div').remove();

            updateLabels($collection);
        });
});
