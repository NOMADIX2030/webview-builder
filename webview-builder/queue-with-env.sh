#!/bin/bash
# Queue 워커 실행 시 빌드 환경 변수 로드
# 사용: ./queue-with-env.sh 또는 bash queue-with-env.sh

# Java (Homebrew openjdk@17)
if [ -d "/opt/homebrew/opt/openjdk@17" ]; then
  export PATH="/opt/homebrew/opt/openjdk@17/bin:$PATH"
elif [ -d "/usr/local/opt/openjdk@17" ]; then
  export PATH="/usr/local/opt/openjdk@17/bin:$PATH"
fi

# Android SDK (Homebrew android-commandlinetools 또는 Android Studio)
if [ -d "$HOME/Library/Android/sdk" ]; then
  export ANDROID_HOME="$HOME/Library/Android/sdk"
elif [ -d "/opt/homebrew/share/android-commandlinetools" ]; then
  export ANDROID_HOME="/opt/homebrew/share/android-commandlinetools"
elif [ -d "/usr/local/share/android-commandlinetools" ]; then
  export ANDROID_HOME="/usr/local/share/android-commandlinetools"
fi

export PATH="$PATH:${ANDROID_HOME:-}/platform-tools:${ANDROID_HOME:-}/cmdline-tools/latest/bin"

cd "$(dirname "$0")"
exec php artisan queue:work "$@"
