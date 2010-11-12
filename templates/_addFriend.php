<h2>Add Friend</h2>
<form method="POST" action="<?php echo $this->get('actionUrl') ?>">
  <input type="hidden" name="csrf" value="<?php echo $this->get('csrf') ?>" />
  <ul>
    <li>
      <label for="first_name">First Name</label><input type="text" name="first_name" value="<?php echo $data['first_name'] ?>"/>
    </li>
    <li>
      <label for="last_name">Last Name</label><input type="text" name="last_name" value="<?php echo $data['last_name'] ?>"/>
    </li>
    <li>
      <label for="nickname">Nickname</label><input type="text" name="nickname" value="<?php echo $data['nickname'] ?>"/>
      <?php if ($this->error('nickname', 'required')): ?>
        <span class="error">Required.</span>
      <?php endif ?>
      <?php if ($this->error('nickname', 'unique')): ?>
        <span class="error">Must be unique among your friends.</span>
      <?php endif ?>
    </li>
    <li>
      <input class="submit" type="submit" value="Create Friend Request" /> <a class="cancel" href="<?php echo $data['cancelUrl'] ?>">Cancel</a>
    </li>
  </ul>
</form>