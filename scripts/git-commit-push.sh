#!/bin/bash
# 웹뷰 앱 빌더 — 상세 커밋 및 푸시
# 실행: ./scripts/git-commit-push.sh

set -e
cd "$(dirname "$0")/.."

echo "=== Git 상태 확인 ==="
git status

echo ""
echo "=== 스테이징 (모든 변경사항) ==="
git add -A

echo ""
echo "=== 스테이징된 변경 통계 ==="
git diff --cached --stat

echo ""
echo "=== 커밋 실행 ==="
git commit -m "feat: 뒤로가기 Snackbar 제거, 2회 연속 종료 + 문서 최신화

- MainActivity: Snackbar 제거, 루트에서 뒤로가기 2회 연속 시 앱 종료 (알림 없음)
- 문서: Blade + Tailwind 반영, 라우팅 경로 수정, shadcn 참조 제거
- AGENTS.md: 최근 작업(뒤로가기) 반영
- PROJECT_STATUS: 뒤로가기 동작, 안드로이드 테스트 완료 반영
- .gitignore: frontend 참조 제거, Blade 단일 앱 반영"

echo ""
echo "=== 푸시 실행 ==="
git push origin main

echo ""
echo "=== 완료 ==="
