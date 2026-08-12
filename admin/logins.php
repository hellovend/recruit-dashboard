<?php
// admin/login.php의 중복 구현이었음 (SQL 인젝션 + 비밀번호 미검증 상태로 방치되어 있었음).
// 로그인 로직을 두 곳에서 따로 관리하지 않도록 실제 로그인 페이지로 위임.
header("Location: login.php");
exit();
