<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/cell-coverage-banner.php';

if (!llama_cell_coverage_update_banner_enabled()) {
    return;
}
?>

<div
    class="site-promotion-banner"
    role="status"
    aria-label="Cell coverage update notice"
>
    <div class="site-promotion-banner-inner">
        <strong>
            Cell coverage is being updated. Coverage information may be incomplete or inaccurate in some areas until the update is complete.
        </strong>
    </div>
</div>
