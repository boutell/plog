<h2>Friend Request</h2>

<p>
Please send the following instructions to your friend via a method you trust&mdash; email, Facebook mail, a Twitter private direct message, anything you consider <strong>private</strong>. Change the text as you see fit.
</p>
<p>
  <em>Why don't you email it for me?</em> Emails from websites are easy to forge and are often discarded as spam. So you should communicate this information to your friend personally and customize the message so they understand it is genuine.
</p>
<blockquote>
  <p>
    Hi <?php echo $data['nickname'] ?>, I'm inviting you to access restricted updates on my plog. To do that, <strong>log into your own plog and click "Accept Friend Request,"</strong> then copy and paste the code below:
  </p>
  <pre><?php echo wordwrap($data['code'], 40, "\n", true) ?></pre>
  <p>
    <em>"That's nice, but I don't have a plog."</em> I suggest that you get one. Plogs are like regular blogs, but you can restrict your posts to trusted friends as you see fit. They are like Facebook or LiveJournal or Twitter, but your content stays under your own control, and no one company controls all of the plogs in the world. It's a good thing. Visit <a href="http://www.boutell.com/plog">http://www.boutell.com/plog</a> for more information.
  </p>
  <p>
    <em>"Can't I just read your RSS feed?"</em> Sure, but my private posts aren't in it. That's because RSS feeds tend to wind up in public services like Google Reader, which is not where I want my private posts to be.
  </p>
  <p>
    <em>"Isn't it a pain reading plogs and regular RSS feeds separately?"</em> Of course. That's why plogs come with a sweet feed reader that shows you both regular RSS feeds and plogs.
  </p>
</blockquote>
