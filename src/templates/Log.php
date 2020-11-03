<?php if (isset($session)): ?>
    <h1><?= $session_meta['url'] ?></h1>
    <?= $this->show_console($session, false) ?>
<?php else: ?>
    <p class="text-right">
        <a class="btn btn-sm btn-danger" href="?remove=all"><i class="icon-times icon-remove icon-white fa fa-times"></i> Remove all</a>
    </p>
    <div class="table-responsive">
        <table class="table table-hover table-bordered table-striped">
            <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Status</th>
                <th>Response</th>
                <th>Time</th>
                <th>URL</th>
                <th>Tools</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($sessions as $session): ?>
                <tr>
                    <td><?= $session['id'] ?></td>
                    <td title="<?= date('r', $session['date']) ?>"><?= EurekaProfiler_Tools::readable_interval(microtime(true) - $session['date']) ?> ago</td>
                    <td><?= $session['status'] ?></td>
                    <td><?= EurekaProfiler_Tools::readable_size($session['response_size']) ?></td>
                    <td><?= EurekaProfiler_Tools::readable_interval($session['time']) ?></td>
                    <td><?= htmlspecialchars($session['url']) ?></td>
                    <td>
                        <a class="btn btn-sm btn-primary" href="?show=<?= $session['id'] ?>"><i class="icon-white icon-search fa fa-search"></i> View</a>
                        <a class="btn btn-sm btn-default" href="?remove=<?= $session['id'] ?>"><i class="icon-times icon-remove fa fa-times"></i> Remove</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="pager pagination">
        <span class="text-muted muted">
             <?= strtr(
                 'Showing {offset} - {end} of {total}',
                 array(
                     '{offset}' => EurekaProfiler_Tools::readable_number($offset),
                     '{end}'    => EurekaProfiler_Tools::readable_number($offset + count($sessions)),
                     '{total}'  => EurekaProfiler_Tools::readable_number($total),
                 )
             ) ?>
        </span>
        <a class="prev btn btn-small" rel="prev nofollow" href="?offset=<?= max($offset - $per_page, 0) ?>">«</a>
        <a class="next btn btn-small" rel="next nofollow" href="?offset=<?= min($offset + $per_page, $total - 1) ?>">»</a>
    </div>
<?php endif; ?>