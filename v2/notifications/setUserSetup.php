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

require_once '../../include/Global.php';
$pg = nsc_initJsonPost();
$username = nsc_postArg('username', 'STR');
$password = nsc_postArg('password', 'STR');
$triggerOnReply = nsc_postArg('triggerOnReply', 'BIT');
$triggerOnMention = nsc_postArg('triggerOnMention', 'BIT');
$triggerKeywords = nsc_postArg('triggerKeywords', 'STR*', array());

nsc_checkLogin($username, $password);
$username = strtolower($username);

nsc_execute($pg, 'BEGIN');
nsc_execute($pg, 'UPDATE notify_user SET match_replies = $1, match_mentions = $2 WHERE username = $3',
   array($triggerOnReply, $triggerOnMention, $username));
nsc_execute($pg, 'DELETE FROM notify_user_keyword WHERE username = $1', array($username));
foreach ($triggerKeywords as $keyword)
{
   nsc_execute($pg, 'INSERT INTO notify_user_keyword (username, keyword) VALUES ($1, $2)',
      array($username, $keyword));
}
nsc_execute($pg, 'COMMIT');

echo json_encode(array('result' => 'success'));
