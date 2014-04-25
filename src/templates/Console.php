<?php
/* @var $session EurekaProfiler_Session */
/* @var $static_url string */


$tabs = array(
    array(
        'id' => 'request',
        'color' => '#588E13',
        'text' => 'Request',
        'summary' => EurekaProfiler_Tools::readable_number(
                                  count($session->request_headers) + count($session->get_data) +
                                  count($session->post_data) + count($session->cookies)
            )
    ),
    array(
        'id' => 'response',
        'color' => '#3769A0',
        'alternate' => '#2B5481',
        'text' => 'Response',
        'summary' => EurekaProfiler_Tools::readable_interval($session->duration, 1)
    ),
    array(
        'id' => 'events',
        'color' => '#949494',
        'text' => 'Events',
        'summary' => EurekaProfiler_Tools::readable_number(count($session->all_events()))
    ),
    array(
        'id' => 'db',
        'color' => '#953FA1',
        'text' => 'Database',
        'summary' => EurekaProfiler_Tools::readable_number(count($session->events_of_type('db'))) . ' Queries'
    ),
    array(
        'id' => 'included',
        'color' => '#B72F09',
        'alternate' => '#9B2700',
        'text' => 'Included',
        'summary' => EurekaProfiler_Tools::readable_number(count($session->loaded_files)) . ' Files'
    ),
);
?>
    <div class="app-console">
        <!--    Assets    -->
        <div class="app-assets">
            <style type="text/css">
                @import url("<?= $static_url ?>/css/console.css");
            </style>
            <script>window.jQuery || document.write('<script src="<?= $static_url ?>/js/jquery.js"><\/script>')</script>
            <script type="text/javascript" src="<?= $static_url ?>/js/console.js"></script>
        </div>

        <!-- TOGGLE -->
        <a class="app-console-toggle">Profiler</a>

        <div class="app-console-content">

            <!-- NAVIGATION TABS -->
            <table class="app-tabs">
                <tr>
                    <?php
                    foreach ($tabs as $tab) :
                        ?>
                        <td class="<?= $tab == $tabs[0] ? 'active' : '' ?>" data-target="<?= $tab['id'] ?>">
                            <h3 style="color: <?= $tab['color'] ?>"><?= $tab['summary'] ?></h3>
                            <h4><?= $tab['text'] ?></h4>
                        </td>
                    <?php
                    endforeach;
                    ?>
                </tr>
            </table>

            <!-- CONTENT -->
            <div class="app-content">
                <?php
                foreach ($tabs as $tab) {
                    $class = $tab == $tabs[0] ? 'active' : '';
                    echo ' <div class="app-tab app-tab-', $tab['id'], ' ', $class . '">';
                    call_user_func("render_{$tab['id']}", $session, $tab);
                    echo '</div>';
                }
                ?>
            </div>

            <!-- FOOTER -->
            <div class="app-footer">
                <div class="app-tools">
                    <a class="app-popup" title="Move profiler to another window">[ ]</a>
                    <a data-consoleh="100" title="Increase console size">[+]</a>
                    <a data-consoleh="-100" title="Decrease console size">[-]</a>
                </div>
                <div style="clear: both"></div>
            </div>
        </div>
    </div>
<?php
if (function_exists('render_request')) {
    return;
}

function basic_row($type, $name, $value = null, $type_color = '')
{
    $data = is_array($name) ? $name : array($name => $value);

    foreach ($data as $key => $value) {
        ?>
        <tr>
            <?php if (!empty($type)): ?>
                <td class="type" style="background: <?= $type_color ?>"><?= $type ?></td>
            <?php endif; ?>
            <td><?= $key ?></td>
            <?php if (isset($value)): ?>
                <td class="more"><?= $value ?></td>
            <?php endif; ?>
        </tr>
    <?php
    }
}

function render_data($data)
{

    if (empty($data)) {
        return '&nbsp;';
    } else {
        if (class_exists('Dump', false)) {
            try {
                return Dump::render_data('', $data, false);
            } catch (Exception $e) {
                return '';
            }
        } else {
            return '<pre>' . print_r($data, true) . '</pre>';
        }
    }
}

function render_chart($rows, $name)
{

    $chart_id = 'app-query-chart-' . mt_rand();
    ?>
    <div class="app-chart" id="<?= $chart_id ?>"></div>
    <script type="text/javascript" src="https://www.google.com/jsapi"></script>
    <script type="text/javascript">
        // Load the Visualization API and the piechart package.
        google.load('visualization', '1.0', {'packages': ['corechart']});

        // Set a callback to run when the Google Visualization API is loaded.
        google.setOnLoadCallback(function () {
            // Create the data table.
            var data = new google.visualization.DataTable();
            data.addColumn('string', <?= json_encode($name) ?>);
            data.addColumn('number', 'Time');
            data.addRows(<?= json_encode($rows) ?>);

            // Set chart options
            var options = {
                width: 200,
                height: 150,
                backgroundColor: 'transparent',
                legend: 'none',
                is3D: true,
                pieSliceText: 'none',
                colors: <?= json_encode(get_theme_colors()); ?>
            };

            // Instantiate and draw our chart, passing in some options.
            var chart = new google.visualization.PieChart(document.getElementById(<?= json_encode($chart_id) ?>));
            chart.draw(data, options);

            //Events
            google.visualization.events.addListener(chart, 'onmouseover', function (e) {
                profiler_select_row(<?= json_encode("#$chart_id") ?>, e.row)
            });
            google.visualization.events.addListener(chart, 'onmouseout', function () {
                profiler_select_row(<?= json_encode("#$chart_id") ?>, -1)
            });
        });
    </script>
<?php
}

function get_theme_colors()
{
    return array('#588E13', '#B72F09', '#D28C00', '#3769A0', '#949494', '#948300', '#E0C600');
}

function render_request(EurekaProfiler_Session $session)
{
    $request_size = 0;
    foreach (array($session->request_headers, $session->get_data, $session->post_data, $session->cookies) as $arr) {
        foreach ($arr as $k => $v) {
            $request_size += strlen($k) + strlen($v);
        }
    }
    ?>
    <table class="summary">
        <tr>
            <td style="background: #588E13;"><h3><?= EurekaProfiler_Tools::readable_size($request_size) ?></h3>
                <h4>Size</h4>
            </td>
            <td style="background: #B72F09;"><h3><?= EurekaProfiler_Tools::readable_number(count($session->get_data)) ?></h3>
                <h4>GET</h4>
            </td>
        </tr>
        <tr>
            <td style="background: #D28C00;" class="border"><h3><?=
                    EurekaProfiler_Tools::readable_number(
                                 count($session->post_data)
                    ) ?></h3>
                <h4>POST</h4>
            </td>
            <td style="background: #3769A0;"><h3><?= EurekaProfiler_Tools::readable_number(count($session->cookies)) ?></h3>
                <h4>Cookie</h4>
            </td>
        </tr>
    </table>
    <?php
    $headers = $session->request_headers;
    unset($headers['COOKIE']);
    ?>
    <div class="data-wrapper">
        <table class="data">
            <?php
            basic_row('HTTP', $headers, '', '#588E13');
            basic_row('GET', $session->get_data, '', '#B72F09');
            basic_row('POST', $session->post_data, '', '#D28C00');
            basic_row('Cookie', $session->cookies, '', '#3769A0');
            ?>
        </table>
    </div>
<?php
}

function render_response(EurekaProfiler_Session $session, $tab)
{
    ?>
    <table class="summary">
        <tr>
            <td style="background: <?= $tab['color'] ?>">
                <h3>
                    <?= EurekaProfiler_Tools::readable_interval($session->duration, 1) ?>
                    (<?php
                    $db = $session->total_query_time();
                    $php = $session->duration - $db;
                    echo round(100 / ($db + $php) * $php, 0), '% PHP, ', round(100 / ($db + $php) * $db, 0) . '% DB';
                    ?>)
                </h3>
                <h4>Load time</h4>
            </td>
        </tr>
        <tr>
            <td style="background: <?= $tab['alternate'] ?>;">
                <h3><?= EurekaProfiler_Tools::readable_size($session->memory_used) ?> / <?= $session->memory_limit ?></h3>
                <h4>Memory used / available</h4>
            </td>
        </tr>
        <tr>
            <td style="background: <?= $tab['color'] ?>;" class="border">
                <h3><?php
                    $var = $session->max_execution_time;
                    echo is_numeric($var) ? EurekaProfiler_Tools::readable_interval($var) : $var
                    ?>
                </h3>
                <h4>Max execution</h4>
            </td>
        </tr>
    </table>
    <div class="data-wrapper">
        <table class="data">
            <?php
            //Response headers
            basic_row('HTTP', $session->response_headers, '', '#588E13');

            //HTML Response
            if (!empty($session->response)) {
                $name = 'Response (' . EurekaProfiler_Tools::readable_size(strlen($session->response)) . ')';
                basic_row('HTML', $name, render_data($session->response), '#3769A0');
            }

            //Tools
            $firebug_loader = "var url='https://getfirebug.com/firebug-lite-debug.js#startOpened',a=document.getElementsByTagName('HEAD').item(0),b=document.createElement('script');b.type='text/javascript';b.src=url;a.appendChild(b);void(0);";
            basic_row('Tools', '<a onclick="' . htmlspecialchars($firebug_loader) . '">Firebug</a>', '', '#D28C00');
            ?>
        </table>
    </div>
<?php
}

function render_events(EurekaProfiler_Session $session, $tab)
{
    if (empty($session->events)) {
        echo '<h2>This panel has no log items.</h2>';
        return;
    }

    $colors = get_theme_colors();

    //Categories summary
    $types_freq = array();
    $types_duration = array();
    $types_color = array();
    foreach ($session->all_events() as $event) {
        $types_freq[$event->type] = isset($types_freq[$event->type]) ? $types_freq[$event->type] + 1 : 1;
        $types_duration[$event->type] = isset($types_duration[$event->type]) ?
            $types_duration[$event->type] + $event->duration : $event->duration;
    }
    $i = 0;
    foreach ($types_freq as $type => $frec) {
        $types_color[$type] = $colors[$i % count($colors)];
        $i++;
    }
    ?>
    <table class="summary">
        <tr>
            <td colspan="99">
                Filter: <select class="app-events-filter">
                    <option value="">all</option>
                    <?php foreach ($types_freq as $type => $frec): ?>
                        <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <?php /*
          <tr>
          <td colspan="99" style="background: #7B3384;padding-top:0">
          <?php
          $chart_rows = array();
          foreach ($session->all_events() as $event) {
          $chart_rows[] = array($event->name, $event->duration);
          }
          echo render_chart($chart_rows, 'Event');
          ?>
          <h4>Time chart</h4>
          </td>
          </tr>
         */
        ?>
        <tr>
            <td colspan="99" style="background: #7B3384;padding-top:0">
                <?php

                /**
                 * @param ProfilerEvent[] $events
                 */
                function _render_events_chart(
                    $events,
                    &$index,
                    $total_duration,
                    $level,
                    $available_height,
                    $column_width,
                    $types_color
                ) {
                    if (empty($events)) {
                        return;
                    }

                    foreach ($events as $event) {
                        $height = $available_height / $total_duration * $event->duration;
                        $top = $available_height / $total_duration * $event->timemark;
                        $css = array(
                            'top:' . $top . '%',
                            'left:' . $level * $column_width . '%',
                            'background:' . $types_color[$event->type],
                            'height:' . $height . '%',
                            'width:' . $column_width . '%',
                        );
                        $title = "$event->type: $event->name" . ($event->duration ? (' (' .
                                                                                     EurekaProfiler_Tools::readable_interval(
                                                                                                  $event->duration
                                                                                     ) . ')') : '');
                        echo '<div style="' . implode(';', $css) . '" data-index="' . $index . '">' . $title . '</div>';
                        $index++;

                        if (!empty($event->children)) {
                            _render_events_chart(
                                $event->children,
                                $index,
                                $total_duration,
                                $level + 1,
                                $available_height,
                                $column_width,
                                $types_color
                            );
                        }
                    }
                }

                ?>
                <div class="app-event-chart">
                    <?php
                    $index = 0;
                    $max_level = 0;
                    $total_duration = $session->duration;
                    foreach ($session->all_events() as $event) {
                        $total_duration = max($total_duration, $event->timemark);
                        $current = $event;
                        for ($i = 1; $current->parent; $i++) {
                            $current = $current->parent;
                        }
                        $max_level = max($max_level, $i);
                    }
                    echo _render_events_chart(
                        $session->events,
                        $index,
                        $total_duration,
                        0,
                        99,
                        100 / $max_level,
                        $types_color
                    );
                    ?>
                </div>
                <h4><a class="app-event-chart-open">Time chart [»]</a></h4>
            </td>
        </tr>

        <tr>
            <?php
            $i = 0;
            $columns = 2;
            foreach ($types_freq as $type => $frec) {
                $class = $i % $columns == 0 && $i >= count($types_freq) - $columns ? 'border' : '';
                ?>
                <td style="background: <?= $types_color[$type] ?>" class="<?= $class ?>">
                    <h3><?= $type ?></h3>
                    <h4><?= EurekaProfiler_Tools::readable_number($frec) ?>, <?=
                        EurekaProfiler_Tools::readable_interval(
                                     $types_duration[$type],
                                         1
                        ) ?></h4>
                </td>
                <?php
                $i++;
                if ($i > 0 && ($i % $columns == 0 || $i == count($types_freq))) {
                    echo '</tr><tr>';
                }
            }
            ?>
        </tr>
    </table>

    <!-- Event Data-->
    <div class="data-wrapper">
        <?php

        /**
         * @param ProfilerEvent[] $events
         */
        function _render_events($events, $level, $types_color)
        {
            if (empty($events)) {
                return;
            }

            echo $level == 0 ? '<table class="data data-events">' : '<table>';
            foreach ($events as $event) {
                $info = $event->name;
                if ($event->data) {
                    $info .= ' <span class="event-data" title="Event data">(' . render_data($event->data) . ')</span>';
                }
                if ($event->duration) {
                    $info .= ' <span class="event-duration" title="Event duration">(' .
                             EurekaProfiler_Tools::readable_interval($event->duration) . ')</span>';
                }
                if ($event->backtrace) {
                    $info .= '<p class="event-backtrace" title="Event backtrace">' . $event->backtrace . '</p>';
                }

                $children = '';
                if (!empty($event->children)) {
                    ob_start();
                    _render_events($event->children, $level + 1, $types_color);
                    $children = ob_get_clean();
                }

                $timemark = '<span class="more" title="Event time">' .
                            EurekaProfiler_Tools::readable_interval($event->timemark) . '</span>';
                basic_row($event->type, $timemark . $info . $children, null, $types_color[$event->type]);
            }
            echo '</table>';
        }

        _render_events($session->events, 0, $types_color);
        ?>
    </div>
<?php
}

function render_db(EurekaProfiler_Session $session)
{
    $queries = $session->events_of_type('db');
    $colors = get_theme_colors();

    if (empty($queries)) {
        echo '<h2>This panel has no log items.</h2>';
        return;
    }
    ?>
    <table class="summary">
        <tr>
            <td style="background: #953FA1;">
                <h3><?= EurekaProfiler_Tools::readable_interval($session->total_query_time()) ?></h3>
                <h4>Total time</h4>
            </td>
        </tr>
        <tr>
            <td style="background: #7B3384;padding-top:0" class="border">
                <?php
                $chart_rows = array();
                $i = 0;
                foreach ($queries as $query) {
                    $i++;
                    $chart_rows[] = array(
                        $i . ': ' . (empty($query->text) ? $query->query : $query->text),
                        $query->duration
                    );
                }
                echo render_chart($chart_rows, 'Query');
                ?>
                <h4>Time chart</h4>
            </td>
        </tr>
    </table>

    <!-- Query Data-->
    <div class="data-wrapper">
        <table class="data data-db">
            <?php
            $i = 0;
            foreach ($queries as $query) :
                /* @var $query ProfilerQuery */

                //EXPLAIN info
                if (empty($query->explain)) {
                    $explain_html = 'No EXPLAIN info';
                } else {
                    $explain_html = array();
                    foreach ($query->explain as $key => $value) {
                        $name = ucwords(str_replace('_', ' ', $key));
                        $explain_html[] = "$name: <strong>$value</strong>";
                    }
                    $explain_html = implode(' &middot ', $explain_html);
                }

                //Query Info       
                if (empty($query->text)) {
                    $query_html = htmlspecialchars($query->query);
                } else {
                    $query_html = $query->text;

                    $original_query = $query->query;
                    if (class_exists('Dump') && method_exists('Dump', 'source')) {
                        $original_query = Dump::source($original_query, 'mysql');
                    }

                    $explain_html =
                        '<a class="show-original-query" title="Show full original query" href="#" onclick="app_show_popup(' .
                        htmlspecialchars(json_encode($original_query)) .
                        ', \'Original query\');return false;">[SHOW]</a>&nbsp;' . $explain_html;
                }

                $html = '<div class="query">' . $query_html . '</div>' .
                        '<div class="query-explain">' . $explain_html . '</div>';

                basic_row(
                    $i + 1,
                    $html,
                    EurekaProfiler_Tools::readable_interval($query->duration),
                    $colors[$i % count($colors)]
                );
                $i++;
            endforeach;
            ?>
        </table>
    </div>
<?php
}

function render_included(EurekaProfiler_Session $session, $tab)
{
    ?>
    <table class="summary">
        <tr>
            <td style="background: <?= $tab['color'] ?>;">
                <h3><?= EurekaProfiler_Tools::readable_number(count($session->loaded_files)) ?></h3>
                <h4>Total files</h4>
            </td>
        </tr>
        <tr>
            <td style="background: <?= $tab['alternate'] ?>;">
                <h3><?= EurekaProfiler_Tools::readable_size($session->total_included_size()) ?></h3>
                <h4>Total size</h4>
            </td>
        </tr>
        <tr>
            <td class="border" style="background: <?= $tab['color'] ?>;padding-top:0">
                <?php
                $chart_rows = array();
                foreach ($session->loaded_files as $file) {
                    $chart_rows[] = array($file->path, $file->size);
                }
                echo render_chart($chart_rows, 'File');
                ?>
                <h4>Size chart</h4>
            </td>
        </tr>
    </table>
    <div class="data-wrapper">
        <table class="data">
            <?php
            $show = array();
            foreach ($session->loaded_files as $file) {
                $show[$file->path] = EurekaProfiler_Tools::readable_size($file->size);
            }
            basic_row('', $show);
            ?>
        </table>
    </div>
<?php
}
