<html>
  <body>
    <?php if ($x) { ?>
      <p>a</p>
      <?php
        foo();
          bar();
      ?>
    <?php } ?>
    <?php foreach ($a as $b): ?>
  <?php echo $b; ?>
    <?php endforeach; ?>
  </body>
</html>
