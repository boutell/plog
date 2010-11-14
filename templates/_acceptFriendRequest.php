<h2>Accept Friend Request</h2>
<p>
  Paste the friend request code you received, then click "Continue" to see the details.
</p>
<form method="POST" action="<?php echo $this->get('actionUrl') ?>">
  <input type="hidden" name="csrf" value="<?php echo $this->get('csrf') ?>" />
  <ul>
    <li>
      <label for="data">Friend Request Code</label><textarea name="code"><?php echo $data['code'] ?></textarea>
      <?php if ($this->error('code', 'required')): ?>
        <span class="error">Required.</span>
      <?php endif ?>
      <?php if ($this->error('code', 'invalid')): ?>
        <span class="error">Invalid. Did you copy and paste the entire code and just the code?</span>
      <?php endif ?>
    </li>
    <li>
      <input class="submit" type="submit" value="Continue" /> <a class="cancel" href="<?php echo $data['cancelUrl'] ?>">Cancel</a>
    </li>
  </ul>
</form>