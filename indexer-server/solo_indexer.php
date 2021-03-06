<?
// WinChatty Server
// Copyright (c) 2013 Brian Luft
//
// Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
// documentation files (the "Software"), to deal in the Software without restriction, including without limitation the
// rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to
// permit persons to whom the Software is furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in all copies or substantial portions of the
// Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
// WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS
// OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
// OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

require_once '../include/Global.php';
require_once 'indexer_util.php';

if (php_sapi_name() !== 'cli')
   die('Must be run from the command line.');

define('LOOP_DELAY_USEC', 250000); # 1/4 sec

startIndex();

function startIndex()
{
   $startTime = time();
   $lastLolUpdate = time() - 50;

   try
   {
      checkInternet();
      $pg = nsc_connectToDatabase();

      while ((time() - $startTime) < TOTAL_TIME_SEC)
      {
         processNewPosts($pg);
         usleep(LOOP_DELAY_USEC);
      }
   }
   catch (Exception $e)
   {
      printf("ERROR: %s\n", $e->getMessage());
   }
}

function processNewPosts($pg)
{
   $newPosts = nsc_query($pg, 'SELECT id, username, parent_id, body FROM new_post_queue ORDER BY id', array());
   foreach ($newPosts as $newPost)
   {
      $id = intval($newPost[0]);
      $username = strval($newPost[1]);
      $parentId = intval($newPost[2]);
      $body = strval($newPost[3]);

      nsc_execute($pg, 'BEGIN', array());
      try
      {
         $newPostId = processNewPost($pg, $id, $username, $parentId, $body);
         printf("Post #%d by \"%s\" in reply to #%d:\n\"%s%s\"\n\n", 
            $newPostId, $username, $parentId, substr($body, 0, 72), strlen($body) > 72 ? '...' : '');
         nsc_execute($pg, 'COMMIT', array());
         logNewPost($pg, $newPostId);
      }
      catch (Exception $e)
      {
         echo 'Error processing new post: ' . $e->getMessage() . "\n";
         nsc_execute($pg, 'ROLLBACK', array());
      }

      nsc_execute($pg, 'DELETE FROM new_post_queue WHERE id = $1', array($id));
   }
}

function processNewPost($pg, $queueId, $username, $parentId, $taggedBody) # returns the new post id
{
   $body = tagsToHtml($taggedBody);
   $bodyC = strtolower(strip_tags($body));
   $id = getNextPostId($pg);
   $threadId = $id;

   if ($parentId === 0) 
   {
      nsc_execute($pg, 'INSERT INTO thread (id, bump_date, date) VALUES ($1, NOW(), NOW())', array($id));
   }
   else
   {
      $threadId = nsc_selectValueOrFalse($pg, 'SELECT thread_id FROM post WHERE id = $1', array($parentId));
      if ($threadId === false)
         throw new Exception('Parent ID does not exist.');
      else
         $threadId = intval($threadId);

      nsc_execute($pg, 'UPDATE thread SET bump_date = NOW() WHERE id = $1', array($threadId));
   }

   nsc_execute($pg, 'INSERT INTO post (id, thread_id, parent_id, author, category, date, body, author_c, body_c) ' .
      'VALUES ($1, $2, $3, $4, 1, NOW(), $5, $6, $7)', array(
      $id, $threadId, $parentId, $username, $body, strtolower($username), $bodyC));

   if (V2_SEARCH_ENGINE == 'duct-tape') 
   {
      try
      {
         $parentAuthor = '';
         if ($parentId !== 0) 
            $parentAuthor = strval(nsc_selectValue($pg, 'SELECT author FROM post WHERE id = $1', array($parentId)));

         dts_index($id, $body, $username, $parentAuthor, 1);
      }
      catch (Exception $e)
      {
         echo 'Error indexing post: ' . $e->getMessage() . "\n";
      }
   }
   else
   {
      nsc_execute($pg, 'INSERT INTO post_index (id, body_c_ts) VALUES ($1, to_tsvector($2))', array($id, $bodyC));
   }

   return $id;
}

function getNextPostId($pg)
{
   $maxPostId = intval(nsc_selectValue($pg, 
      'SELECT CASE WHEN COUNT(*) = 0 THEN 0 ELSE MAX(id) END FROM post', array()));
   $maxNukedId = intval(nsc_selectValue($pg, 
      'SELECT CASE WHEN COUNT(*) = 0 THEN 0 ELSE MAX(id) END FROM nuked_post', array()));
   return max($maxPostId, $maxNukedId) + 1;
}

function tagsToHtml($text)
{
   $html = $text;

   # simple replacements
   $html = str_replace('&', '&amp;', $html);
   $html = str_replace('<', '&lt;', $html);
   $html = str_replace('>', '&gt;', $html);
   $html = str_replace("\r\n", '<br>', $html);
   $html = str_replace("\n", '<br>', $html);
   $html = str_replace("\r", '<br>', $html);

   $html = escapeTagsInsideCodeTag($html);

   $complexReplacements = array(
      # array(from-start-tag, from-end-tag, to-start-tag, to-end-tag)
      array('r{', '}r', '<span class="jt_red">', '</span>'),
      array('g{', '}g', '<span class="jt_green">', '</span>'),
      array('b{', '}b', '<span class="jt_blue">', '</span>'),
      array('y{', '}y', '<span class="jt_yellow">', '</span>'),
      array('e\\[', '\\]e', '<span class="jt_olive">', '</span>'),
      array('l\\[', '\\]l', '<span class="jt_lime">', '</span>'),
      array('n\\[', '\\]n', '<span class="jt_orange">', '</span>'),
      array('p\\[', '\\]p', '<span class="jt_pink">', '</span>'),
      array('q\\[', '\\]q', '<span class="jt_quote">', '</span>'),
      array('s\\[', '\\]s', '<span class="jt_sample">', '</span>'),
      array('-\\[', '\\]-', '<span class="jt_strike">', '</span>'),
      array('i\\[', '\\]i', '<i>', '</i>'),
      array('\\/\\[', '\\]\\/', '<i>', '</i>'),
      array('b\\[', '\\]b', '<b>', '</b>'),
      array('\\*\\[', '\\]\\*', '<b>', '</b>'),
      array('_\\[', '\\]_', '<u>', '</u>'),
      array('o\\[', '\\]o', '<span class="jt_spoiler" onclick="return doSpoiler(event);">', '</span>'),
      array('\\/{{', '}}\\/', '<pre class="jt_code">', '</pre>')
   );

   foreach ($complexReplacements as $r)
   {
      $pattern = '/' . $r[0] . '(.*?)' . $r[1] . '/';
      $html = preg_replace($pattern, $r[2] . '$1' . $r[3], $html);
   }

   # replace orphaned opening shacktags, close them at the end of the post.
   foreach ($complexReplacements as $r)
   {
      $pattern = '/' . $r[0] . '/';
      $count = 0;
      $html = preg_replace($pattern, $r[2], $html, -1, $count);
      for ($i = 0; $i < $count; $i++)
         $html .= $r[3];
   }

   return $html;
}

function escapeTagsInsideCodeTag($orig)
{
   $inCodeTag = false;
   $len = strlen($orig);
   $newStr = '';
   for ($i = 0; $i < $len; $i++)
   {
      $tag = substr($orig, $i, 3);
      $ch = $orig[$i];
      if (!$inCodeTag && $tag == '/{{')
      {
         $newStr .= $tag;
         $inCodeTag = true;
         $i += 2;
      }
      else if ($inCodeTag && $tag == '}}/')
      {
         $newStr .= $tag;
         $inCodeTag = false;
         $i += 2;
      }
      else if ($inCodeTag)
      {
         if ($ch == '[')
            $newStr .= '&lsqb;';
         else if ($ch == ']')
            $newStr .= '&rsqb;';
         else if ($ch == '{')
            $newStr .= '&lcub;';
         else if ($ch == '}')
            $newStr .= '&rcub;';
         else
            $newStr .= $ch;
      }
      else
      {
         $newStr .= $ch;
      }
   }
   return $newStr;
}
