<nav class="registration-role-tabs" aria-label="Choose account type">
    <?php foreach (['register.php'=>['bi-mortarboard','Student'], 'register_college.php'=>['bi-building','College'], 'register_university.php'=>['bi-bank','University']] as $route=>[$icon,$label]): ?>
        <a href="<?= $route ?>" class="<?= $current_page === $route ? 'active' : '' ?>"<?= $current_page === $route ? ' aria-current="page"' : '' ?>><i class="bi <?= $icon ?>" aria-hidden="true"></i><span><?= $label ?></span></a>
    <?php endforeach; ?>
</nav>
