<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Support\ReportRegistry;
use Filament\Pages\Page;

class ReportsHub extends Page
{
    use InteractsWithAdminPermissions;

    protected static function adminPermissionModule(): string
    {
        return 'reports_hub';
    }

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Report Center';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'admin.reports.hub';

    public string $search = '';

    public function getSubheading(): ?string
    {
        return 'Browse, filter and export structured reports. Sub-admins see only permitted reports.';
    }

    public function getGroupedReports(): array
    {
        $grouped = ReportRegistry::accessibleByCategory();
        $categories = ReportRegistry::categories();
        $search = trim(mb_strtolower($this->search));
        $result = [];

        foreach ($categories as $key => $label) {
            if (empty($grouped[$key])) {
                continue;
            }

            $reports = $grouped[$key];

            if ($search !== '') {
                $reports = array_values(array_filter(
                    $reports,
                    function (array $item) use ($search): bool {
                        $definition = $item['definition'];
                        $haystack = mb_strtolower(
                            ($definition['label'] ?? '').' '.
                            ($definition['description'] ?? '')
                        );

                        return str_contains($haystack, $search);
                    }
                ));

                if ($reports === []) {
                    continue;
                }
            }

            $result[] = [
                'key' => $key,
                'label' => $label,
                'meta' => ReportRegistry::categoryMeta($key),
                'reports' => $reports,
            ];
        }

        return $result;
    }

    public function getReportStats(): array
    {
        $groups = ReportRegistry::accessibleByCategory();
        $total = 0;

        foreach ($groups as $reports) {
            $total += count($reports);
        }

        return [
            'total' => $total,
            'categories' => count($groups),
        ];
    }
}
