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

class ThreadParser extends Parser
{
   # The HTML from the last getThreadTree and getThreadBodies calls, for debugging.
   public static $threadTreeHtml = '';
   public static $threadBodiesHtml = '';

   public function getThread($threadID)
   {
      # $threadID might be a reply ID instead of a root thread ID.
      # get_thread_tree() can handle it.
      $tree = $this->getThreadTree($threadID);

      # From $tree we can grab the real root thread ID.
      $threadID = $tree['replies'][0]['id'];
      $bodies   = $this->getThreadBodies($threadID);
      
      # Build a hashtable from the bodies.
      $bodies_table = array();
      foreach ($bodies['replies'] as $body)
         $bodies_table[$body['id']] = array('body' => $body['body'], 'date' => $body['date']);
      
      # Add the bodies to the tree.
      foreach ($tree['replies'] as $i => $reply)
      {
         if (isset($bodies_table[$reply['id']]))
         {
            $reply['body'] = $bodies_table[$reply['id']]['body'];
            $reply['date'] = $bodies_table[$reply['id']]['date'];
            $tree['replies'][$i] = $reply;
         }
      }
      
      return $tree;
   }
   
   public function getThreadBodies($threadID)
   {
      $threadID = intval($threadID);
      $url      = "https://www.shacknews.com/frame_laryn.x?root=$threadID";
      $html     = $this->download($url, true);

      self::$threadBodiesHtml = $html;

      if (strpos($html, '</html>') === false)
      {
         throw new Exception('Shacknews thread bodies HTML ended prematurely.');
      }

      $this->init($html);

      $o = array( # Output
         'replies'      => array());
      
      while ($this->peek(1, '<div id="item_') !== false)
      {
         $reply = array(
            'category' => false,
            'id'       => false,
            'author'   => false,
            'body'     => false,
            'date'     => false);
            
         $reply['id'] = $this->clip(
            array('<div id="item_', '_'),
            '">');
         $reply['category'] = $this->clip(
            array('<div class="fullpost', 'fpmod_', '_'),
            ' ');
         $reply['author'] = trim(html_entity_decode($this->clip(
            array('<span class="author">', '<span class="user">', '<a rel="nofollow" href="/user/', '>'),
            '</a>')));
         $reply['body'] = $this->makeSpoilersClickable($this->clip(
            array('<div class="postbody">', '>'),
            '</div>'));
         $reply['date'] = strip_tags($this->clip(
            array('<div class="postdate">', '>'),
            'T</div')) . 'T';
         
         $o['replies'][] = $reply;
      }
      
      return $o;
   }
   
   public function checkContentId($html)
   {
      $contentTypeIdPrefix = '<input type="hidden" name="content_type_id" id="content_type_id" value="';
      $contentTypeIdPos = strpos($html, $contentTypeIdPrefix);
      $contentTypeId = false;
      if ($contentTypeIdPos !== false)
      {
         $i = $contentTypeIdPos + strlen($contentTypeIdPrefix);
         $contentTypeIdStr = '';
         while ($html[$i] != '"')
         {
            $contentTypeIdStr .= $html[$i];
            $i++;
         }
         $contentTypeId = intval($contentTypeIdStr);
      }
      if ($contentTypeId !== false && $contentTypeId != 2 && $contentTypeId != 17)
         throw new Exception('This post is not in the main chatty. ' . $contentTypeId);
   }

   public function getThreadTree($threadID)
   {
      $threadID = intval($threadID);
      $url      = "https://www.shacknews.com/chatty?id=$threadID";
      $html     = $this->download($url);

      self::$threadTreeHtml = $html;

      if (strpos($html, '</html>') === false)
      {
         throw new Exception('Shacknews thread tree HTML ended prematurely.');
      }

      if (strpos($html, '<p class="be_first_to_comment">') !== false)
      {
         # This ID is in the future.
         throw new Exception('This post is in the future.');
      }

      self::checkContentId($html);

      $this->init($html);

      $this->seek(1, '<div class="threads">');
      
      $story_id = 0;
      $story_name = 'Latest Chatty';

      $thread = $this->parseThreadTree($this, false);
      $thread['story_id'] = $story_id;
      $thread['story_name'] = $story_name;
      
      return $thread;
   }

   public function parseThreadTree(&$p, $stopAtFullPost = true)
   {
      $thread = array(
         'body'          => false,
         'category'      => false,
         'id'            => false,
         'author'        => false,
         'date'          => false,
         'preview'       => false,
         'reply_count'   => false,
         'last_reply_id' => false,
         'replies'       => array());
      
      #
      # Post metadata
      #
      # <div class="fullpost fpmod_ontopic fpauthor_203312"> 
      # <div class="postnumber"><a title="Permalink" href="laryn.x?id=21464158#itemanchor_21464158">#176</a></div>
      # <div class="refresh"><a title="Refresh Thread" target="dom_iframe" href="/frame_laryn.x?root=21464158&id=21464158&mode=refresh">Refresh Thread</a></div>
      # <div class="postmeta">
      # <span class="author">
      # By:	<span class="user"><a rel="nofollow" href="/user/quazar/posts" 
      #  target="_blank" title="quazar's comments">quazar</a></span>  
      #  <a class="shackmsg" rel="nofollow" href="/messages?method=compose&amp;to=quazar" 
      #  target="_blank" title="Shack message quazar"><img src="/images/envelope.gif" 
      #  alt="shackmsg this person" /></a><a class="lightningbolt" rel=\"nofollow\" 
      #  href="/mercury"><img src="http://cf.shacknews.com/images/bolt.gif" alt=
      #  "This person is cool!" /></a>
      # </span>	
      $thread['category'] = $p->clip(
         array('<div class="fullpost', 'fpmod_', '_'),
         ' ');
      $thread['id'] = $p->clip(
         array('<a rel="nofollow" title="Permalink" href=', 'id=', '='),
         '#');
      $thread['author'] = html_entity_decode(trim($p->clip(
         array('<span class="author">', '<span class="user">', '<a rel="nofollow" href="/user/', '>'),
         '</a>')));
      $thread['body'] = $this->makeSpoilersClickable(trim($p->clip(
         array('<div class="postbody">', '>'),
         '</div>')));
      $thread['date'] = strip_tags($p->clip(
         array('<div class="postdate">', '>'),
         'T</div')) . 'T';

      # Read the rest of the replies in this thread.
      $depth = 0;
      $last_reply_id = intval($thread['id']);
      $next_thread = $p->peek(1, '<div class="fullpost op');
      if ($next_thread === false)
         $next_thread = $p->len;
      
      while (true)
      {
         $next_reply = $p->peek(1, '<div class="oneline');
         if ($next_reply === false || ($stopAtFullPost === true && $next_reply > $next_thread))
            break;
         
         $reply = array(
            'category' => false,
            'id'       => false,
            'author'   => false,
            'preview'  => false,
            'depth'    => $depth);
         
         if (count($thread['replies']) == 0)
            $reply['date'] = $thread['date'];
         #
         # Reply
         #
         # <div class="oneline oneline9 hidden olmod_ontopic olauthor_203312">
         #          <div class="treecollapse">
         #             <a class="open" href="#" onClick="toggle_collapse(21464158); ...
         #          </div>
         # <a href="?id=21464158" onClick="return clickItem( 21464158, ...
         #    <span class="oneline_body">
         #       These two opening themes to Ghost in the Shell always give me the chills; 
         # http://www.youtube.com/watch...	</span> : 
         #    <a href="/profile/Mr.+Goodwrench" class="oneline_user ">
         #       Mr. Goodwrench   </a>
         #
         $reply['category'] = $p->clip(
            array('<div class="oneline', 'olmod_', '_'),
            ' ');
         $reply['id'] = $p->clip(
            array('<a class="shackmsg" rel="nofollow" href="?id=', 'id=', '='),
            '"');
         $reply['preview'] = trim(collapse_whitespace($p->clip(
            array('<span class="oneline_body">', '>'),
            '</span> : </a><span class="oneline_user "')));
         $reply['preview'] = self::removeSpoilers($reply['preview']);
         $reply['author'] = html_entity_decode(trim($p->clip(
            array('<span class="oneline_user', '>'),
            '</span>')));
            
         if (intval($reply['id']) > $last_reply_id)
            $last_reply_id = intval($reply['id']);
         
         # Determine the next level of depth.
         while (true)
         {
            $next_li = $p->peek(1, '<li ');
            $next_ul = $p->peek(1, '<ul>');
            $next_end_ul = $p->peek(1, '</ul>');
            
            if ($next_li === false)
               $next_li = $next_thread;
            if ($next_ul === false)
               $next_ul = $next_thread;
            if ($next_end_ul === false)
               $next_end_ul = $next_thread;
               
            $next = min($next_li, $next_ul, $next_end_ul);

            if ($next == $next_thread)
            {
               # This thread has no more replies.
               break;
            }
            else if ($next == $next_li)
            {
               # Next reply is on the same depth level.
               break;
            }
            else if ($next == $next_ul)
            {
               # Next reply is underneath this one.
               $depth++;
            }
            else if ($next == $next_end_ul)
            {
               # Next reply is above this one.
               $depth--;
            }
            
            $p->cursors[1] = $next + 1;
         }

         $thread['replies'][] = $reply;
      }
      
      $thread['replies'][0]['body'] = $thread['body'];
      $thread['preview']       = self::previewFromBody($thread['body']);
      $thread['last_reply_id'] = $last_reply_id;
      $thread['reply_count']   = count($thread['replies']);
      return $thread;
   }

   public static function previewFromBody($body)
   {
      $preview = self::removeSpoilers($body, false);
      $preview = self::strReplaceAll("<br />", " ", $preview);
      $preview = self::strReplaceAll("<br/>", " ", $preview);
      $preview = self::strReplaceAll("<br>", " ", $preview);
      $preview = self::strReplaceAll("\n", " ", $preview);
      $preview = self::strReplaceAll("\r", " ", $preview);
      $preview = self::strReplaceAll("  ", " ", $preview);
      $preview = strip_tags($preview, '<span><b><i><u>');
      return $preview;
   }
   
   private static function strReplaceAll($needle, $replacement, $haystack)
   {
      while (strstr($haystack, $needle))
         $haystack = str_replace($needle, $replacement, $haystack);
      return $haystack;
   }
   
   public static function removeSpoilers($text, $isReply = true)
   {
      $spoilerSpan    = null;
      
      #if ($isReply)
         #$spoilerSpan = 'span class="jt_spoiler" onclick="return doSpoiler( event );">';
      #else
         #$spoilerSpan = 'span class="jt_spoiler" onclick="this.className = ";">';
         
      $spoilerSpan = 'span class="jt_spoiler"';
         
      $spoilerSpanLen = strlen($spoilerSpan);
      $span           = 'span ';
      $spanLen        = strlen($span);
      $endSpan        = '/span>';
      $endSpanLen     = strlen($endSpan);
      $replaceStr     = "_______";
      $out            = '';
      $inSpoiler      = false;
      $depth          = 0;
      
      # Split by < to get all the tags separated out.
      foreach (explode('<', $text) as $i => $chunk)
      {
         if ($i == 0)
         {
            # The first chunk does not start with or contain a <, so we can
            # just copy it directly to the output.
            $out .= $chunk;
         }
         else if ($inSpoiler)
         {
            if (strncmp($chunk, $span, $spanLen) == 0)
            {
               # Nested Shacktag.
               $depth++;
            }
            else if (strncmp($chunk, $endSpan, $endSpanLen) == 0)
            {
               # End of a Shacktag.
               $depth--;

               # If the depth has dropped back to zero, then we found the end
               # of the spoilered text.
               if ($depth == 0)
               {
                  $out      .= substr($chunk, $endSpanLen);
                  $inSpoiler = false;
               }
            }
         }
         else
         {
            if (strncmp($chunk, $spoilerSpan, $spoilerSpanLen) == 0)
            {
               # Beginning of a spoiler.
               $inSpoiler = true;
               $depth     = 1;
               $out      .= $replaceStr;
            }
            else
            {
               $out .= '<' . $chunk;
            }
         }
      }
      
      return $out;
   }
   
   private function makeSpoilersClickable($text)
   {
      return str_replace('return doSpoiler(event);', "this.className = '';", $text);
   }
}

function ThreadParser()
{
   return new ThreadParser();
}
