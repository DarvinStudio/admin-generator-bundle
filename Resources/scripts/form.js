$(() => {
    const SELECTORS = {
        form: 'form.js-ajax'
    };

    const replaceContent = (html) => {
        let $html = $(html);

        let $content = $html.find('#js-content');

        if (!$content.length) {
            $content = $html;
        }

        $('#js-content').html($content);

        $(document).trigger('app.html', {
            $html: $content
        });
    };
    const replaceUrl = (url) => {
        if (url && 'undefined' !== typeof history) {
            history.pushState(null, null, url);
        }
    };

    $('body')
        .on('click', SELECTORS.form + ' [type="submit"][name]', (e) => {
            let $submit = $(e.currentTarget);

            $submit.closest(SELECTORS.form).append('<input name="' + $submit.attr('name') + '" type="hidden">');
        })
        .on('submit', SELECTORS.form, (e) => {
            e.preventDefault();

            let $form = $(e.currentTarget);

            let options = $form.data();

            if (options.submitted || options.redirecting) {
                return;
            }

            $form.data('submitted', true);

            App.startPreloading();

            let url  = $form.attr('action') || options.url || '',
                type = ($form.attr('method') || 'get').toLowerCase();

            let params = {
                url:  url,
                type: type
            };

            if ('get' === type || 'undefined' === typeof FormData) {
                params.data = $form.serialize();
            } else {
                $.extend(params, {
                    data:        new FormData($form[0]),
                    contentType: false,
                    processData: false
                });
            }

            $.ajax(params).done((data) => {
                if ('get' === type) {
                    let parts = [location.pathname];

                    if (params.data) {
                        parts.push('?', params.data);
                    }

                    replaceUrl(parts.join(''));
                }
                if (!$.isPlainObject(data)) {
                    replaceContent(data);

                    return;
                }

                App.notify(data.message, data.success ? 'success' : 'error');

                if (data.success && options.reloadPage && !$('#js-content').data('not-reloadable')) {
                    App.startPreloading('form');

                    let pageUrl = data.redirectUrl || '';

                    $.ajax({
                        url:   pageUrl,
                        cache: false
                    }).done(replaceContent).always(() => {
                        replaceUrl(pageUrl);
                    }).always(() => {
                        App.stopPreloading('form');
                    }).fail(App.onAjaxFail);

                    return;
                }
                if (App.redirect(data.redirectUrl)) {
                    $form.data('redirecting', true);
                }
                if (data.html) {
                    let $html   = $(data.html),
                        $target = $form;

                    if (options.target) {
                        $target = $form.closest(options.target);

                        if (!$target.length) {
                            $target = $(options.target + ':first');
                        }
                    }
                    if ($target.length) {
                        $target.replaceWith($html);

                        $(document).trigger('app.html', {
                            $html: $html.parent()
                        });
                    }
                }
            }).always(() => {
                $form.removeData('submitted');
            }).fail(App.onAjaxFail);
    });
});
