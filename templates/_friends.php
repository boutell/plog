<ul class="friends">
  <?php foreach ($data['friends'] as $friend): ?>
    <li class="friend">
      <ul>
        <li class="name"><?php echo $friend['firstName'] . ' ' . $friend['lastName' ] . ' (' . $friend['nickname'] . ')' ?></li>
        <li class="actions"><a class="delete" href="<?php echo $friend['deleteUrl'] ?>">Delete</a></li>
      </ul>
    </li>
  <?php endforeach ?>
</ul>
<script type="text/javascript" charset="utf-8">
  $('.actions .delete').click(function() {
    $('.actions .confirm-delete')
    return confirm('Are you sure you want to delete this friend?');
  });
</script>