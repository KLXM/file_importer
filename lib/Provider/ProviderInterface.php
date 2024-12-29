<?php
namespace Klxm\FileImporter\Provider;

interface ProviderInterface
{
    public function search(string $query, int $page = 1, array $options = []): array;
    public function download(string $url, string $filename): bool;
    public function getName(): string;
    public function isConfigured(): bool;
    public function getConfigFields(): array;
}
