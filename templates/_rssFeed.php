<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0">
  <channel>
  <title><?php echo $data['title']</title>
  <description><?php echo $data['description'] ?></description>
  <link><?php echo $data['url'] ?></link>
  <?php foreach ($data['posts'] as $post): ?>
  	<item>
  		<title><?php echo $post['title'] ?></title>
  		<description><?php echo $post['body'] ?></description>
  		<link><?php echo $post['url'] ?></link>
  		<guid><?php echo $post['slug'] ?></guid>
  		<pubDate><?php echo $post['published'] ?></pubDate>
  	</item>
  <?php endforeach ?>
  </channel>
</rss>
