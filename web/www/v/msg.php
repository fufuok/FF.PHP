<?php defined('FF') or die('404'); ?>
<!DOCTYPE html>
<html lang="zh-CN" xml:lang="zh-CN">
<head>
    <meta charset="UTF-8" />
    <?php include('head.php'); ?>
    <title>消息_<?php echo WEB_NAME; ?></title>
</head>
<body id="msg">
<table border="0" class="bodytable">
    <tbody>
    <tr>
        <td valign="middle">
            <div class="pagemsg">
                <p><a href="/"><img src="/v/images/logo.png" alt="FF"></a></p>
                <p class="c-default">（<?php echo isset($msg) ? $msg : '-'; ?>）</p>
            </div>
        </td>
    </tr>
    </tbody>
</table>
</body>
</html>