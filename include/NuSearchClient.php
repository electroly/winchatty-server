<?
# WinChatty Server
# Copyright (C) 2013 Brian Luft
# 
# This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public 
# License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later 
# version.
# 
# This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied 
# warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more 
# details.
# 
# You should have received a copy of the GNU General Public License along with this program; if not, write to the Free 
# Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

# Convenience
function nsc_initJsonGet()
{
   global $_GET;
   nsc_jsonHeader();
   nsc_assertGet();
   return nsc_connectToDatabase();
}

# Convenience
function nsc_initJsonPost()
{
   global $_POST;
   nsc_jsonHeader();
   nsc_assertPost();
   return nsc_connectToDatabase();
}

function nsc_assert($condition, $message)
{
   if (!$condition)
      nsc_die('ERR_SERVER', $message);
}

function nsc_getArg($parName, $parType, $def = null)
{
   global $_GET;
   return nsc_arg($_GET, $parName, $parType, $def);
}

function nsc_postArg($parName, $parType, $def = null)
{
   global $_POST;
   return nsc_arg($_POST, $parName, $parType, $def);
}

function nsc_arg($args, $parName, $parType, $def = null)
{
   $parType = str_replace('*', '+?', $parType);
   $isMissing = !isset($args[$parName]) || ($args[$parName] === '');

   if (strpos($parType, '?') !== false)
   {
      $parType = str_replace('?', '', $parType);
      if ($isMissing)
         return $def;
   }
   else if ($isMissing)
      nsc_die('ERR_ARGUMENT', "Missing argument '$parName'.");

   $max = 4294967295;
   if (strpos($parType, ',') !== false)
   {
      $parts = explode(',', $parType);
      $max = intval($parts[1]);
      $parType = $parts[0];
   }

   $isList = false;
   if (strpos($parType, '+') !== false)
   {
      $parType = str_replace('+', '', $parType);
      $isList = true;
   }

   $arg = $args[$parName];
   $ok = false;
   $argItems = explode(',', $arg);

   if ($isList && count($argItems) > $max)
      nsc_die('ERR_ARGUMENT', "Too many arguments in the list '$parName'.");

   foreach ($argItems as $argItem)
   {
      switch ($parType)
      {
         case 'INT':
            $pattern = '/^[0-9]+$/';
            $ok = preg_match($pattern, $argItem) === 1;
            if (!$isList && intval($argItem) > $max)
               nsc_die('ERR_ARGUMENT', "Argument '$parName' is out of range.  Maximum: $max.");
            break;
         case 'BIT':
            $ok = ($argItem == 'true' || $argItem == 'false');
            break;
         case 'STR':
            $ok = true;
            break;
         case 'DAT':
            $ok = (strtotime($argItem) !== false);
            break;
         case 'MOD':
            $ok = ($argItem == 'ontopic' || $argItem == 'nws' || $argItem == 'stupid' || $argItem == 'political' || $argItem == 'tangent' || $argItem == 'informative');
            break;
         case 'MBX':
            $ok = ($argItem == 'inbox' || $argItem == 'sent');
            break;
         case 'PET':
            $ok = ($argItem == 'nuked' || $argItem == 'unnuked' || $argItem == 'flagged');
            break;
         case 'MPT':
            $ok = ($argItem == 'unmarked' || $argItem == 'pinned' || $argItem == 'collapsed');
            break;
         default:
            nsc_die('ERR_ARGUMENT', "Invalid parameter type '$parType'.");
            break;
      }
      if (!$ok)
      {
         nsc_die('ERR_SERVER', "Argument value for parameter '$parName' is invalid. Expected type: $parType.");
      }
   }

   # Enum data types are returned as-is (as strings)
   switch ($parType)
   {
      case 'MOD':
      case 'MBX':
      case 'PET':
      case 'MPT':
         $parType = 'STR';
         break;
   }

   if ($isList && $parType == 'INT')
   {
      $intList = array();
      foreach ($argItems as $str)
         $intList[] = intval($str);
      return $intList;
   }
   else if (!$isList && $parType == 'INT')
   {
      return intval($arg);
   }
   else if ($isList && $parType == 'DAT')
   {
      $timeList = array();
      foreach ($argItems as $str)
         $timeList[] = strtotime($str);
      return $timeList;
   }
   else if (!$isList && $parType == 'DAT')
   {
      return strtotime($arg);
   }
   else if ($isList && $parType == 'BIT')
   {
      $boolList = array();
      foreach ($argItems as $str)
         $boolList[] = ($str == 'true');
      return $boolList;
   }
   else if (!$isList && $parType == 'BIT')
   {
      return strval($arg) == 'true';
   }
   else if ($isList && $parType == 'STR')
   {
      return $argItems;
   }
   else if (!$isList && $parType == 'STR')
   {
      return strval($arg);
   }
   else
   {
      nsc_die('ERR_ARGUMENT', 'Unrecognized parameter type.');
   }
}

function nsc_jsonHeader()
{
   header('Content-type: application/json');
   header('Access-Control-Allow-Origin: *');
}

function nsc_assertGet()
{
   if ($_SERVER['REQUEST_METHOD'] !== 'GET')
      nsc_die('ERR_ARGUMENT', 'Must be a GET request.');
}

function nsc_assertPost()
{
   if ($_SERVER['REQUEST_METHOD'] !== 'POST')
      nsc_die('ERR_ARGUMENT', 'Must be a POST request.');
}

function nsc_connectToDatabase() # postgresql
{
   $pg = pg_connect('dbname=chatty user=nusearch password=nusearch');
   if ($pg === false)
      nsc_die('ERR_SERVER', "Failed to connect to chatty database.");
   return $pg;
}

function nsc_disconnectFromDatabase($pg) # void
{
   if (!is_null($pg) && $pg !== false)
      pg_close($pg);
}

function nsc_die($code, $message)
{
   if (is_string($code) && is_string($message))
      die(json_encode(array('error' => true, 'code' => $code, 'message' => $message)));
   else
      die(json_encode(array('error' => true, 'code' => 'ERR_SERVER', 'message' => 'Invalid call to nsc_die().')));
}

function nsc_selectValueOrFalse($pg, $sql, $args) # value or false
{
   $row = nsc_selectRowOrFalse($pg, $sql, $args);
   return $row === false ? false : $row[0];
}

function nsc_selectValue($pg, $sql, $args) # value
{
   $ret = nsc_selectValueOrFalse($pg, $sql, $args);
   if ($ret === false)
      nsc_die('ERR_SERVER', "SQL query returned zero rows.");
   else
      return $ret;
}

function nsc_selectRowOrFalse($pg, $sql, $args) # dict or false
{
   $args = nsc_preProcessSqlArgs($args);
   $rs = pg_query_params($pg, $sql, $args);
   if ($rs === false)
      nsc_die('ERR_SERVER', "selectValue failed.");
   $row = pg_fetch_row($rs);
   if ($row === false)
      return false;
   else
      return $row;
}

function nsc_selectRow($pg, $sql, $args) # dict
{
   $ret = nsc_selectRowOrFalse($pg, $sql, $args);
   if ($ret === false)
      nsc_die('ERR_SERVER', "SQL query returned zero rows.");
   else
      return $ret;
}

function nsc_selectArray($pg, $sql, $args) # array of scalar values
{
   $args = nsc_preProcessSqlArgs($args);
   $rs = pg_query_params($pg, $sql, $args);
   if ($rs === false)
      nsc_die('ERR_SERVER', "selectValue failed.");
   $ret = array();
   while (true)
   {
      $row = pg_fetch_row($rs);
      if ($row === false)
         break;
      else
         $ret[] = $row[0];
   }
   return $ret;
}

function nsc_query($pg, $sql, $args) # array of rows
{
   $args = nsc_preProcessSqlArgs($args);
   $rs = pg_query_params($pg, $sql, $args);
   if ($rs === false)
      nsc_die('ERR_SERVER', "selectAll failed.");
   $ret = array();
   while (true)
   {
      $row = pg_fetch_row($rs);
      if ($row === false)
         break;
      else
         $ret[] = $row;
   }
   return $ret;
}

function nsc_execute($pg, $sql, $args) # void
{
   $args = nsc_preProcessSqlArgs($args);
   if (pg_query_params($pg, $sql, $args) === false)
      nsc_die('ERR_SERVER', "SQL execute failed.");
}

function nsc_preProcessSqlArgs($args) # array
{
   $newArgs = array();
   foreach ($args as $arg)
   {
      if ($arg === true || $arg === false)
         $newArgs[] = $arg ? 1 : 0;
      else
         $newArgs[] = $arg;
   }
   return $newArgs;
}

function nsc_previewFromBody($body)
{
   $preview = nsc_removeSpoilers($body, false);
   $preview = nsc_strReplaceAll("<br />", " ", $preview);
   $preview = nsc_strReplaceAll("<br/>", " ", $preview);
   $preview = nsc_strReplaceAll("<br>", " ", $preview);
   $preview = nsc_strReplaceAll("\n", " ", $preview);
   $preview = nsc_strReplaceAll("\r", " ", $preview);
   $preview = nsc_strReplaceAll("  ", " ", $preview);
   $preview = strip_tags($preview, '<span><b><i><u>');
   return $preview;
}

function nsc_strReplaceAll($needle, $replacement, $haystack)
{
   while (strstr($haystack, $needle))
      $haystack = str_replace($needle, $replacement, $haystack);
   return $haystack;
}

function nsc_removeSpoilers($text)
{
   $spoilerSpan    = 'span class="jt_spoiler"';
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

function nsc_flagIntToString($num)
{
   $category = 'ontopic';
   switch ($num)
   {
      case 1: $category = 'ontopic'; break;
      case 2: $category = 'nws'; break;
      case 3: $category = 'stupid'; break;
      case 4: $category = 'political'; break;
      case 5: $category = 'offtopic'; break;
      case 6: $category = 'informative'; break;
      case 7: $category = 'nuked'; break;
   }
   return $category;
}

function nsc_newPostFromRow($row)
{
   return array(
      'id' => intval($row[0]),
      'threadId' => intval($row[1]),
      'parentId' => intval($row[2]),
      'author' => strval($row[3]),
      'category' => nsc_flagIntToString($row[4]),
      'date' => nsc_date(strtotime($row[5])),
      'body' => strval($row[6])
   );
}

function nsc_getPosts($pg, $idList)
{
   $id = intval($id);
   $idListStr = implode(',', $idList);
   $rows = nsc_query($pg, 
      "SELECT id, thread_id, parent_id, author, category, date, body FROM post WHERE id IN ($idListStr)", 
      array());
   return array_map('nsc_newPostFromRow', $rows);
}

function nsc_getThread($pg, $id, $possiblyMissing = false)
{
   $id = intval($id);
   $threadId = nsc_selectValueOrFalse($pg, 'SELECT thread_id FROM post WHERE id = $1', array($id));
   if ($threadId === false)
   {
      if ($possiblyMissing)
         return false;
      else
         nsc_die('ERR_SERVER', "The post $id does not exist.");
   }
   $bumpDate = nsc_selectValueOrFalse($pg, 'SELECT bump_date FROM thread WHERE id = $1', array($threadId));
   if ($bumpDate === false)
   {
      if ($possiblyMissing)
         return false;
      else
         nsc_die('ERR_SERVER', "The thread $threadId does not exist.");
   }
   $rows = nsc_query($pg, 
      'SELECT id, thread_id, parent_id, author, category, date, body FROM post WHERE thread_id = $1', 
      array($threadId));
   return array(
      'threadId' => $threadId,
      'posts' => array_map('nsc_newPostFromRow', $rows)
   );
}

function nsc_getThreadIds($pg, $id, $possiblyMissing = false)
{
   $id = intval($id);
   $threadId = nsc_selectValueOrFalse($pg, 'SELECT thread_id FROM post WHERE id = $1', array($id));
   if ($threadId === false)
   {
      if ($possiblyMissing)
         return false;
      else
         nsc_die('ERR_SERVER', "The post $id does not exist.");
   }
   $bumpDate = nsc_selectValueOrFalse($pg, 'SELECT bump_date FROM thread WHERE id = $1', array($threadId));
   if ($bumpDate === false)
   {
      if ($possiblyMissing)
         return false;
      else
         nsc_die('ERR_SERVER', "The thread $threadId does not exist.");
   }
   $ids = nsc_selectArray($pg, 'SELECT id FROM post WHERE thread_id = $1', array($threadId));
   return array(
      'threadId' => $threadId,
      'postIds' => $ids
   );
}

function nsc_getPostRange($pg, $startId, $count, $reverse = false)
{
   $startId = intval($startId);
   $count = intval($count);
   if ($reverse)
   {
      $rows = nsc_query($pg,
         'SELECT id, thread_id, parent_id, author, category, date, body FROM post WHERE id <= $1 ORDER BY id DESC LIMIT $2',
         array($startId, $count));
   }
   else
   {
      $rows = nsc_query($pg,
         'SELECT id, thread_id, parent_id, author, category, date, body FROM post WHERE id >= $1 ORDER BY id LIMIT $2',
         array($startId, $count));
   }
   return array_map('nsc_newPostFromRow', $rows);
}

$_nsc_stemmer = false;
$_nsc_cache = array();
$_nsc_cache_count = 0;
function nsc_stem($word)
{
   global $_nsc_stemmer;
   global $_nsc_cache;
   global $_nsc_cache_count;
   if ($_nsc_stemmer === false)
      $_nsc_stemmer = new PorterStemmer();
   if (isset($_nsc_cache[$word]))
      return $_nsc_cache[$word];
   $stem = substr($_nsc_stemmer->Stem($word), 0, 15);
   $stemLen = strlen($stem);
   for ($i = 0; $i < $stemLen; $i++)
      if (ord($stem[$i]) < 33 || ord($stem[$i]) > 126)
         $stem[$i] = '_';
   if ($_nsc_cache_count < 5000)
   {
      $_nsc_cache[$word] = $stem;
      $_nsc_cache_count++;
   }
   return $stem;
}

$_nsc_stopStems = false;
$_nsc_turnTheseIntoSpace = "~!@#$%^&*()_+`-={}|[]\\:\";'<>?,./\r\n\t";
function nsc_convertTextToStems($text) # possibly empty array of stems
{
   global $_nsc_stopStems;
   global $_nsc_turnTheseIntoSpace;

   if ($_nsc_stopStems === false)
   {
      $_nsc_stopStems = array("a"=>1,"abl"=>1,"about"=>1,"across"=>1,"after"=>1,"all"=>1,"almost"=>1,"also"=>1,"am"=>1,
         "among"=>1,"an"=>1,"and"=>1,"ani"=>1,"ar"=>1,"as"=>1,"at"=>1,"be"=>1,"becaus"=>1,"been"=>1,"but"=>1,"by"=>1,
         "can"=>1,"cannot"=>1,"could"=>1,"dear"=>1,"did"=>1,"do"=>1,"doe"=>1,"either"=>1,"els"=>1,"ever"=>1,"everi"=>1,
         "for"=>1,"from"=>1,"get"=>1,"got"=>1,"had"=>1,"ha"=>1,"have"=>1,"he"=>1,"her"=>1,"him"=>1,"hi"=>1,"how"=>1,
         "howev"=>1,"i"=>1,"if"=>1,"in"=>1,"into"=>1,"is"=>1,"it"=>1,"just"=>1,"least"=>1,"let"=>1,"like"=>1,"mai"=>1,
         "me"=>1,"might"=>1,"most"=>1,"must"=>1,"my"=>1,"neither"=>1,"no"=>1,"nor"=>1,"not"=>1,"of"=>1,"off"=>1,
         "often"=>1,"on"=>1,"onli"=>1,"or"=>1,"other"=>1,"our"=>1,"own"=>1,"rather"=>1,"said"=>1,"sai"=>1,"she"=>1,
         "should"=>1,"sinc"=>1,"so"=>1);
   }

   $text = strtolower(strip_tags($text));
   $chars = str_split($text);
   
   $text = '';
   foreach ($chars as $ch)
   {
      if (empty($ch))
         continue;
      else if (strpos($_nsc_turnTheseIntoSpace, $ch) !== false)
         $text .= ' ';
      else
         $text .= $ch;
   }

   $words = explode(' ', $text);
   $stems = array_values(array_unique(array_map('nsc_stem', explode(' ', $text))));
   $filteredStems = array();
   foreach ($stems as $stem)
      if (strlen($stem) > 1 && !isset($_nsc_stopStems[$stem]))
         $filteredStems[] = $stem;

   return $filteredStems;
}

function nsc_search($pg, $terms, $author, $parentAuthor, $category, $offset, $limit, $oldestFirst)
{
   $model = array(
      'terms' => $terms,
      'author' => $author,
      'parentAuthor' => $parentAuthor,
      'category' => $category,
      'offset' => $offset,
      'limit' => $limit,
      'oldestFirst' => $oldestFirst
   );

   $categoryStr = $model['category'];
   $category = false;
   switch ($model['category'])
   {
      case 'ontopic': $category = 1; break;
      case 'nws': $category = 2; break;
      case 'stupid': $category = 3; break;
      case 'political': $category = 4; break;
      case 'tangent': $category = 5; break;
      case 'informative': $category = 6; break;
   }
   $model['category'] = $category;

   $limit = $model['limit'];
   $offset = $model['offset'];
   $sql = "SELECT post.id, post.thread_id, post.parent_id, post.author, post.category, post.date, post.body FROM post ";
   $where = '';
   $args = array();
   $num = 1;
   $prev = false;
   $join = '';

   if ($model['terms'] != '')
   {
      if ($prev)
         $where .= " AND ";
      $where .= " post_index.body_c_ts @@ plainto_tsquery('english', " . '$' . $num++ . ") ";
      $join .= ' INNER JOIN post_index ON post.id = post_index.id ';
      $args[] = $model['terms'];
      $prev = true;
   }

   if ($model['author'] != '')
   {
      if ($prev)
         $where .= " AND ";
      $where .= ' post.author_c = $' . $num++ . ' ';
      $args[] = strtolower($model['author']);
      $prev = true;
   }

   if ($model['parentAuthor'] != '')
   {
      if ($prev)
         $where .= " AND ";
      $sql .= ' INNER JOIN post AS post2 ON post.parent_id = post2.id ';
      $where .= ' post2.author_c = $' . $num++ . ' ';
      $args[] = strtolower($model['parentAuthor']);
      $prev = true;
   }

   if ($model['category'] !== false)
   {
      if ($prev)
         $where .= " AND ";
      $where .= ' post.category = $' . $num++ . ' ';
      $args[] = intval($model['category']);
      $prev = true;
   }

   $sql .= $join;
   $sql .= ' WHERE ' . $where;
   if ($oldestFirst)
      $sql .= ' ORDER BY post.id LIMIT $' . $num++;
   else
      $sql .= ' ORDER BY post.id DESC LIMIT $' . $num++;
   $sql .= ' OFFSET $' . $num++;
   $args[] = $limit;
   $args[] = $offset;

   $rs = pg_query_params($pg, $sql, $args);
   if ($rs === false)
      nsc_die('ERR_SERVER', 'Failed to execute SQL query: ' . $sql);

   $results = array();
   while (true)
   {
      $row = pg_fetch_row($rs);
      if ($row === false)
         break;

      $results[] = array(
         'id' => intval($row[0]),
         'threadId' => intval($row[1]),
         'parentId' => intval($row[2]),
         'author' => $row[3],
         'category' => nsc_flagIntToString($row[4]),
         'date' => nsc_date(strtotime($row[5])),
         'body' => $row[6]
      );
   }
   return $results;
}

function nsc_date($time)
{
   $str = str_replace('+00:00', 'Z', gmdate('c', $time));
   if (strlen($str) != 20)
      nsc_die('ERR_SERVER', 'Formatted timestamp was not 20 characters long: ' . $str);
   return $str;
}

function nsc_getClientSession($pg, $token)
{
   $row = nsc_selectRowOrFalse($pg, 'SELECT username, client_code FROM client_session WHERE token = $1', array($token));
   if ($row === false)
      nsc_die('ERR_INVALID_TOKEN', 'Invalid client session token.');
   else
      return array('username' => $row[0], 'client_code' => $row[1]);
}

function nsc_getShackerId($pg, $username)
{
   if (empty($username))
      nsc_die('ERR_SERVER', 'Empty username passed to nsc_getShackerId.');
   $username = strtolower($username);

   $id = nsc_selectValueOrFalse($pg, 'SELECT id FROM shacker WHERE username = $1', array($username));
   if ($id === false)
   {
      nsc_execute($pg, 
         'INSERT INTO shacker (username, filter_nws, filter_stupid, filter_political, filter_tangent) VALUES ($1, true, true, true, true)', 
         array($username));
      return nsc_getSharedId($pg, $username);
   }
   else
   {
      return intval($id);
   }
}

function nsc_markTypeIntToString($markType)
{
   if ($markType == 0)
      return 'unmarked';
   else if ($markType == 1)
      return 'pinned';
   else if ($markType == 2)
      return 'collapsed';
   else
      nsc_die('ERR_SERVER', 'Invalid marked post type value.');
}

function nsc_markTypeStringToInt($markType)
{
   if ($markType == 'unmarked')
      return 0;
   else if ($markType == 'pinned')
      return 1;
   else if ($markType == 'collapsed')
      return 2;
   else
      nsc_die('ERR_SERVER', 'Invalid marked post type value.');
}

function nsc_handleException($e)
{
   $message = $e->getMessage();

   if (trim(strtolower($message)) == 'unable to log into user account.')
      nsc_die('ERR_INVALID_LOGIN', 'Invalid login.');
   else
      nsc_die('ERR_SERVER', $message);   
}
