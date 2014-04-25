(function($) {
    $(function() {
        $('.app-console').each(function() {
            var $current = $(this);

            //Fix margin
            $current.resize(function() {
                $('html').css('marginBottom', $current.height() + "px");
            });

            //Console toggle
            $current.find('.app-console-toggle').click(function() {
                var src_h = $current.height();
                $current.toggleClass('active');
                var dest_h = $current.height();

                var dest = $current.is('.active');
                $current.addClass('active');

                $current.height(src_h).animate({
                    height: dest_h
                }, 250, function() {
                    $current.css('height', '');
                    $current.toggleClass('active', dest);
                    
                $current.resize();
                });                
            });

            //Tab selection
            $current.find('[data-target]').click(function() {
                var target = $(this).data('target');

                //Set active tab
                $current.find('[data-target]').removeClass('active');
                $(this).addClass('active');

                //Show tab content
                $current.find('.app-tab').each(function() {
                    $(this).toggleClass('active', $(this).is('.app-tab-' + target));
                });

            });

            //Console size
            $current.find('[data-consoleh]').click(function() {
                $target = $current.find('.app-content');

                $target.height($target.height() + $(this).data('consoleh'));
                
                $current.resize();
            });

            //Popup button
            $current.find('.app-popup').click(function() {
                var html = $('<div>').append($current.clone()).html();
                app_show_popup(html, 'Profiler');
            });

            //Events filter
            $current.find('.app-events-filter').change(function() {
                var current_filter = $(this).val();

                $current.find('.data-events tr').each(function() {
                    var visible = current_filter == '' || $(this).find('.type').first().text() == current_filter;
                    $(this).toggle(visible, 'fast').toggleClass('filtered', visible);
                });

                //Show rows that have visible elements on it
                $current.find('.data-events tr').has('.filtered').show('fast');
            });

            //Events chart
            $current.find('.app-event-chart div').each(function() {
                $(this).attr('title', $(this).text());
            }).hover(function() {
                profiler_select_row(this, $(this).data('index'));
            }, function() {
                profiler_select_row(this, -1);
            });
            $current.find('.app-event-chart-open').click(function() {
                var assets = $current.find('.app-assets').html();
                var html = $('<div>').append($(this).closest('.app-tab').find('.app-event-chart').clone()).html();
                app_show_popup(assets + html, 'Events');
            });
        })
    });
})(jQuery);



function profiler_select_row(src, index) {
    $(src).closest('.app-tab').find('.data tr').each(function(i) {
        $(this).toggleClass('active', i == index);

        if ($(this).is('.active')) {
            $(this).get(0).scrollIntoView();
        }
    });
}

function app_show_popup(body, title) {
    var popup = window.open('', title);
    var doc = popup.document;
    doc.write('<html><head><title>' + title + '</title></head>');
    doc.write('<body class="popup">');
    doc.write(body);
    doc.write('</body></html>');
    doc.close();

    if (popup.focus) {
        popup.focus();
    }

    return popup;
}