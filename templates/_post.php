<h2>New Post</h2>
<form method="POST" action="<?php echo $this->get('actionUrl') ?>">
  <input type="hidden" name="csrf" value="<?php echo $this->get('csrf') ?>" />
  <ul>
    <li class="title">
      <label for="title">Title</label><input type="text" name="title" value="<?php echo $data['title'] ?>"/>
      <?php if ($this->error('title', 'required')): ?>
        <span class="error">Required.</span>
      <?php endif ?>
    </li>
    <li class="body">
      <label for="body">Body</label><textarea name="body"><?php echo $data['body'] ?></textarea>
      <?php if ($this->error('body', 'required')): ?>
        <span class="error">Required.</span>
      <?php endif ?>
    </li>
    <li>
      <ul class="publicity">
        <li><?php echo $this->radio('Everyone (Public)', 'publicity', 'public') ?></li>
        <li><?php echo $this->radio('All Friends', 'publicity', 'friends') ?></li>
        <li class="some"><?php echo $this->radio('Certain Friends Only', 'publicity', 'some') ?>
        <span class="select-groups"><?php echo $this->multipleSelect('Select Groups of Friends', 'fgroups', $data['fgroupOptions']) ?></span></li>
        <li><?php echo $this->radio('Just Me (Private)', 'publicity', 'private') ?></li>
      </ul>
    </li>
    <li>
      <input class="submit" type="submit" value="Post" /> <a class="cancel" href="<?php echo $data['cancelUrl'] ?>">Cancel</a>
    </li>
  </ul>
</form>
<script type="text/javascript" charset="utf-8">
  aMultipleSelect('.some', { 'remove': 'x', 'add': 'Add a New Group'});

  $('ul.publicity input').change(function() {
    if ($('.some input:checked').length)
    {
      $('.some .select-groups').show();
    }
    else
    {
      $('.some .select-groups').hide();
    }
  });
  $('ul.publicity input').change();
</script>