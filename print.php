<?php

function printButton(string $label = 'Print', string $icon = 'assets/icons/print.png'): void {
    ?>
    <button type="button" class="asset-detail-action-btn" onclick="window.print()">
        <img src="<?= htmlspecialchars($icon) ?>" alt="">
        <span><?= htmlspecialchars($label) ?></span>
    </button>
    <?php
}