<ul class="index-posts">
  <?php foreach ($data['posts'] as $post): ?>
    <li>
      <ul class="post">
        <li class="title"><a href="<?php echo $post['url'] ?>"><?php echo $post['title'] ?></a></li>
        <li class="masthead">
          <?php $when = strtotime($post['published']) ?>
          <?php echo date('F jS, Y', strtotime($post['published'])) ?> by 
          <?php if ($post['nickname'] === '_me'): ?>
            Me
          <?php else: ?>
            <?php echo $post['first_name'] ?> <?php echo $post['last_name'] ?> (<?php echo $post['nickname'] ?>)
          <?php endif ?>
        </li>
        <li class="body"><?php echo $post['body'] ?></li>
      </ul>
    </li>
  <?php endforeach ?>
</ul>
