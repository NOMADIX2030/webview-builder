<?php

namespace App\Services;

class BuildConfigService
{
    /**
     * 도메인에서 앱 이름 추출 (첫 글자 대문자)
     * 예: https://myplatform.com → Myplatform
     */
    public function generateAppName(string $webUrl): string
    {
        $host = $this->extractHost($webUrl);
        $name = explode('.', $host)[0] ?? $host;
        return ucfirst(strtolower($name));
    }

    /**
     * 도메인에서 패키지 ID 추출
     * 예: https://myplatform.com → com.myplatform.app
     */
    public function generatePackageId(string $webUrl): string
    {
        $host = $this->extractHost($webUrl);
        $name = explode('.', $host)[0] ?? $host;
        $sanitized = preg_replace('/[^a-z0-9]/', '', strtolower($name));
        return 'com.' . ($sanitized ?: 'app') . '.app';
    }

    /**
     * iOS Bundle ID 생성 (기본값: package_id와 동일)
     */
    public function generateBundleId(string $webUrl): string
    {
        return $this->generatePackageId($webUrl);
    }

    /**
     * 개인정보처리방침 URL 생성
     */
    public function generatePrivacyPolicyUrl(string $webUrl): string
    {
        $origin = $this->getOrigin($webUrl);
        return rtrim($origin, '/') . '/privacy';
    }

    /**
     * 지원/문의 URL 생성
     */
    public function generateSupportUrl(string $webUrl): string
    {
        $origin = $this->getOrigin($webUrl);
        return rtrim($origin, '/') . '/contact';
    }

    /**
     * 1단계 데이터로 2단계 추천값 전체 생성
     */
    public function generateStep2FromWebUrl(string $webUrl): array
    {
        return [
            'appName' => $this->generateAppName($webUrl),
            'packageId' => $this->generatePackageId($webUrl),
            'bundleId' => $this->generateBundleId($webUrl),
            'privacyPolicyUrl' => $this->generatePrivacyPolicyUrl($webUrl),
            'supportUrl' => $this->generateSupportUrl($webUrl),
            'versionName' => '1.0.0',
            'versionCode' => 1,
        ];
    }

    private function extractHost(string $url): string
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? $url;
        return preg_replace('/^www\./', '', $host);
    }

    private function getOrigin(string $url): string
    {
        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? 'localhost';
        $port = $parsed['port'] ?? null;
        $origin = $scheme . '://' . $host;
        if ($port && ! in_array($port, [80, 443])) {
            $origin .= ':' . $port;
        }
        return $origin;
    }
}
