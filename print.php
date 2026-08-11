<?php

function printButton(string $label = 'Print', string $icon = 'assets/icons/print.png', string $class = 'action-btn'): void {
    ?>
    <button type="button" class="<?= htmlspecialchars($class) ?>" onclick="window.print()">
        <img src="<?= htmlspecialchars($icon) ?>" alt="">
        <span><?= htmlspecialchars($label) ?></span>
    </button>
    <?php
}
