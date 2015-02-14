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

require_once 'include/Global.php';

$model = array();

$model['terms'] = isset($_GET['q']) ? trim($_GET['q']) : '';
$model['author'] = isset($_GET['a']) ? trim($_GET['a']) : '';
$model['parentAuthor'] = isset($_GET['pa']) ? trim($_GET['pa']) : '';
$model['category'] = isset($_GET['c']) ? $_GET['c'] : '';
$model['home'] = $model['terms'] == '' &&
                 $model['author'] == '' &&
                 $model['parentAuthor'] == '' &&
                 $model['category'] == '';

$defaultUptime = ' 14:23:24 up 1 day, 49 min,  1 user,  load average: 1.65, 2.42, 2.16';

if ($model['home'])
{
   $frontPageDataFilePath = search_data_directory . 'FrontPageData';
   if (file_exists($frontPageDataFilePath))
      $data = unserialize(file_get_contents($frontPageDataFilePath));
   else
      $data = array(
         'thread_count'       => 0,
         'post_count'         => 0,
         'nuked_post_count'   => 0,
         'pending_nuke_count' => 0,
         'oldest_post_date'   => '2013-01-01',
         'newest_post_date'   => '2013-01-01',
         'database_size'      => 0,
         'disk_free'          => 0,
         'last_index'         => '2013-01-01',
         'last_index_count'   => 0,
         'total_time_sec'     => 270,
         'uptime'             => $defaultUptime
      );

   $model['lastIndexResult'] = sprintf("%s new posts",
      number_format($data['last_index_count']));

   #$model['postCount'] = intval($data['post_count']);
   #$model['threadCount'] = intval($data['thread_count']);
   #$model['nukeCount'] = intval($data['nuked_post_count']);
   #$model['pendingCount'] = intval($data['pending_nuke_count']);
   #$model['lowestDate'] = date('M j, Y g:i A', $data['oldest_post_date']);
   #$model['highestDate'] = date('F j, Y', $data['newest_post_date']);
   $model['databaseSize'] = intval($data['database_size']) / 1073741824.0;
   $model['freeSpace'] = intval($data['disk_free']) / 1048576.0;
   $secondsAgo = time() - $data['last_index'];
   $lastIndexStr = date('g:i A', $data['last_index']);
   if ($secondsAgo < 60)
      $model['lastIndex'] = "$lastIndexStr (&lt; 1 minute ago)";
   else
   {
      $minutesAgo = floor($secondsAgo / 60.0);
      if ($minutesAgo == 1)
         $model['lastIndex'] = "$lastIndexStr (1 minute ago)"; 
      else if ($minutesAgo < 60)
         $model['lastIndex'] = "$lastIndexStr ($minutesAgo minutes ago)"; 
      else
         $model['lastIndex'] = "$lastIndexStr"; 
   }
}

$loadArray = explode("load average:", isset($data['uptime']) ? $data['uptime'] : $defaultUptime);
$load = trim($loadArray[1]);

?>
<!DOCTYPE html>
<html>
   <head>
      <meta charset="utf-8"> 
      <title>WinChatty NuSearch</title>
      <link href="//fonts.googleapis.com/css?family=Source+Sans+Pro:400,700,400italic,700italic" rel="stylesheet" type="text/css">
      <link href="nusearch_style.css" rel="stylesheet" type="text/css">
      <script src="//code.jquery.com/jquery-1.11.2.min.js"></script>
   </head>
   <body>
      <div id="formContainer" style="position: fixed; width: 100%;">
         <a href="/nusearch"><img src="/img/App128.png" id="logo" alt="WinChatty NuSearch"></a>
         <form action="/nusearch" method="get">
            <table id="formTable">
               <tr id="formHeader">
                  <td>Search:</td>
                  <td>Author:</td>
                  <td>Parent author:</td>
                  <td>Moderation flag:</td>
                  <td></td>
               </tr>
               <tr>
                  <td><input class="text" autofocus type="text" name="q" value="<?=isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''?>"></td>
                  <td><input class="text" type="text" name="a" value="<?=isset($_GET['a']) ? htmlspecialchars($_GET['a']) : ''?>"></td>
                  <td><input class="text" type="text" name="pa" value="<?=isset($_GET['pa']) ? htmlspecialchars($_GET['pa']) : ''?>"></td>
                  <td>
                     <select name="c" id="category">
                        <option value="">&nbsp;</option>
                        <option value="on-topic">on-topic</option>
                        <option value="not work safe">not work safe</option>
                        <option value="stupid">stupid</option>
                        <option value="political/religious">political/religious</option>
                        <option value="tangent">tangent</option>
                        <option value="informative">informative</option>
                     </select>
                     <script>
                        $("#category").val("<?=htmlspecialchars($model['category'])?>");
                     </script>
                  </td>
                  <td><input type="submit" value="Search" class="button" id="btnSubmit"></td>
               </tr>
            </table>
         </form>
      </div>
      <div style="height: 55px;"></div>
      <? if ($model['home']) { ?>

      <div id="intro">
         @electroly's sweet links:
         <ul>
            <li><span style="color: red; font-weight: bold;">NEW!</span> <a class="link" href="/notifications">Install WinChatty Notifier</a>
               (push notifications for Windows)</li>
            <li><a class="link" href="/search">Old Shacknews-based comment search</a> (use this if NuSearch is too slow)</li>
            <li><a class="link" href="https://github.com/electroly/winchatty-server">WinChatty on GitHub</a></li>
            <li><a class="link" href="http://shackwiki.com/wiki/Lamp#Full_Installer">Install the Lamp desktop client</a> 
               (recommended)</li>
            <li><a class="link" href="//s3.amazonaws.com/winchatty/WinChatty-3.1.air">Install the WinChatty desktop 
               client</a> (obsolete; requires <a class="link" href="http://get.adobe.com/air">Adobe AIR</a>)</li>
         </ul>
         <br>
         <table id="stats">
            <tr>
               <td>Last cycle ended:</td>
               <td><?=$model['lastIndex']?></td>
            </tr>
            <tr>
               <td>Last cycle result:</td>
               <td><?=$model['lastIndexResult']?></td>
            </tr>
            <tr>
               <td>Database size:</td>
               <td><?=number_format($model['databaseSize'], 1)?> GB</td>
            </tr>
            <tr>
               <td>Load averages:</td>
               <td><?=$load?></td>
            </tr>
         </table>
      </div>

      <? } else { ?>

      <br>
      <center>
         <div id="results"></div>
      </center>
      <div style="text-align: center;" id="loading"><img src="/img/ajax-loader.gif"></div>
      <center>
         <input type="submit" value="Load more" class="button" style="display: none; width: 100px;" id="loadMore" onclick="loadMore()">
         <br><br><br>
      </center>
      <script>
         <?
            $q = urlencode($model['terms']);
            $a = urlencode($model['author']);
            $pa = urlencode($model['parentAuthor']);
            $c = urlencode($model['category']);
            $pageURL = "/nusearch_page?q=$q&a=$a&pa=$pa&c=$c";
         ?>

         $.ajax({ 
            type: "GET",
            url: "<?=$pageURL?>",
            async: true,
            cache: true,
            dataType: "html",
            success: function(html) {
               $("#loading").hide();
               $("#results").append($(html));
            }
         });

         var offset = 0;

         function loadMore() {
            $("#loadMore").hide();
            $("#loading").show();

            offset += 35;

            $.ajax({ 
               type: "GET",
               url: "<?=$pageURL?>&offset=" + offset,
               async: true,
               cache: true,
               dataType: "html",
               success: function(html) {
                  $("#loading").hide();
                  $("#results").append($(html));
               }
            });            
         }
      </script>
      <? } ?>
   </body>
</html>
