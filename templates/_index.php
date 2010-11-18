<ul class="index-posts">
  <?php foreach ($data['posts'] as $post): ?>
    <li>
      <ul class="post">
        <li class="title"><a href="<?php echo $post['url'] ?>"><?php echo $post['title'] ?></a>
          <?php if (isset($post['editUrl'])): ?>
            <a class="edit" href="<?php echo $post['editUrl'] ?>">Edit</a>
          <?php endif ?>
        </li>
        <li class="masthead">
          <?php $when = strtotime($post['published']) ?>
          <?php echo date('F jS, Y', strtotime($post['published'])) ?> by 
          <?php if ($post['nickname'] === '_me'): ?>
            Me
          <?php else: ?>
            <?php echo $post['first_name'] ?> <?php echo $post['last_name'] ?> (<?php echo $post['nickname'] ?>)
          <?php endif ?>
        </li>
        <?php // Posts are prefiltered HTML, so in this one instance ?>
        <?php // we undo the escaping that occurs when data is passed ?>
        <?php // to a template ?>
        <li class="body"><?php echo html_entity_decode($post['body']) ?></li>
      </ul>
    </li>
  <?php endforeach ?>
</ul>
