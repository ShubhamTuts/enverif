<?php

namespace App\Core\Connectors\Drivers;

use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Connectors\DTO\{ConnectorAction, ConnectorResult};
use App\Core\Email\OAuthTokenService;
use App\Models\ConnectorConnection;

final class GoogleSheetsConnector extends AbstractConnector
{
    public function __construct(private readonly OAuthTokenService $tokens) {}

    public function id(): string
    {
        return 'google_sheets';
    }

    public function label(): string
    {
        return 'Google Sheets';
    }

    public function actions(): array
    {
        return [
            new ConnectorAction(
                'get_spreadsheet',
                'Get spreadsheet metadata including sheet titles and IDs.',
                RiskLevel::Read,
                ['type' => 'object', 'properties' => [
                    'spreadsheet_id' => ['type' => 'string'],
                ], 'required' => ['spreadsheet_id']],
            ),
            new ConnectorAction(
                'get_values',
                'Read cell values from a range (A1 notation).',
                RiskLevel::Read,
                ['type' => 'object', 'properties' => [
                    'spreadsheet_id' => ['type' => 'string'],
                    'range' => ['type' => 'string', 'description' => 'e.g. Sheet1!A1:D50'],
                    'major_dimension' => ['type' => 'string', 'description' => 'ROWS or COLUMNS'],
                ], 'required' => ['spreadsheet_id', 'range']],
            ),
            new ConnectorAction(
                'append_values',
                'Append rows to a sheet range. Requires approval unless autonomous external writes are enabled.',
                RiskLevel::ExternalWrite,
                ['type' => 'object', 'properties' => [
                    'spreadsheet_id' => ['type' => 'string'],
                    'range' => ['type' => 'string', 'description' => 'e.g. Sheet1!A:D'],
                    'values' => ['type' => 'array', 'description' => '2D array of rows', 'items' => ['type' => 'array', 'items' => []]],
                    'value_input_option' => ['type' => 'string', 'description' => 'RAW or USER_ENTERED'],
                ], 'required' => ['spreadsheet_id', 'range', 'values']],
            ),
            new ConnectorAction(
                'update_values',
                'Overwrite values in a range. Requires approval unless autonomous external writes are enabled.',
                RiskLevel::ExternalWrite,
                ['type' => 'object', 'properties' => [
                    'spreadsheet_id' => ['type' => 'string'],
                    'range' => ['type' => 'string'],
                    'values' => ['type' => 'array', 'items' => ['type' => 'array', 'items' => []]],
                    'value_input_option' => ['type' => 'string'],
                ], 'required' => ['spreadsheet_id', 'range', 'values']],
            ),
            new ConnectorAction(
                'clear_values',
                'Clear values in a range (keeps formatting). Destructive.',
                RiskLevel::Destructive,
                ['type' => 'object', 'properties' => [
                    'spreadsheet_id' => ['type' => 'string'],
                    'range' => ['type' => 'string'],
                ], 'required' => ['spreadsheet_id', 'range']],
            ),
            new ConnectorAction(
                'create_spreadsheet',
                'Create a new Google Spreadsheet. Requires approval unless autonomous external writes are enabled.',
                RiskLevel::ExternalWrite,
                ['type' => 'object', 'properties' => [
                    'title' => ['type' => 'string'],
                    'sheet_title' => ['type' => 'string'],
                ], 'required' => ['title']],
            ),
            new ConnectorAction(
                'list_recent',
                'List recent spreadsheets via Google Drive (files the connection can access).',
                RiskLevel::Read,
                ['type' => 'object', 'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Optional Drive name contains filter'],
                    'limit' => ['type' => 'integer'],
                ]],
            ),
        ];
    }

    public function configurationSchema(): array
    {
        return [
            'credentials' => [
                'client_secret' => [
                    'label' => 'Google OAuth client secret',
                    'secret' => true,
                    'required' => true,
                    'help' => 'Stored encrypted. Enable Google Sheets API + Google Drive API and create a Web OAuth client.',
                ],
            ],
            'fields' => [
                'client_id' => [
                    'label' => 'Google OAuth client ID',
                    'required' => true,
                    'help' => 'Register the Enverif OAuth callback URL on the Google Cloud Web client.',
                ],
                'default_spreadsheet_id' => [
                    'label' => 'Default spreadsheet ID',
                    'required' => false,
                    'help' => 'Optional. Agents can still pass spreadsheet_id per action.',
                ],
            ],
        ];
    }

    public function test(ConnectorConnection $connection): bool
    {
        try {
            return $this->request($connection)
                ->get('https://www.googleapis.com/drive/v3/files', [
                    'q' => "mimeType='application/vnd.google-apps.spreadsheet' and trashed=false",
                    'pageSize' => 1,
                    'fields' => 'files(id)',
                ])
                ->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function execute(ConnectorConnection $connection, string $action, array $arguments): ConnectorResult
    {
        $this->action($action);
        $request = $this->request($connection);
        $spreadsheetId = trim((string) ($arguments['spreadsheet_id'] ?? data_get($connection->configuration, 'default_spreadsheet_id', '')));
        $range = trim((string) ($arguments['range'] ?? ''));
        $values = $arguments['values'] ?? [];
        if (! is_array($values)) {
            $values = [];
        }
        $inputOption = strtoupper(trim((string) ($arguments['value_input_option'] ?? 'USER_ENTERED'))) ?: 'USER_ENTERED';
        if (! in_array($inputOption, ['RAW', 'USER_ENTERED'], true)) {
            $inputOption = 'USER_ENTERED';
        }

        return match ($action) {
            'get_spreadsheet' => $this->requireId($spreadsheetId, function () use ($request, $spreadsheetId) {
                return ConnectorResult::success(
                    $request->get('https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($spreadsheetId), [
                        'fields' => 'spreadsheetId,properties,sheets.properties',
                    ])->throw()->json()
                );
            }),
            'get_values' => $this->requireId($spreadsheetId, function () use ($request, $spreadsheetId, $range, $arguments) {
                if ($range === '') {
                    return ConnectorResult::failure('range is required');
                }
                $major = strtoupper(trim((string) ($arguments['major_dimension'] ?? 'ROWS'))) ?: 'ROWS';

                return ConnectorResult::success(
                    $request->get(
                        'https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($spreadsheetId).'/values/'.rawurlencode($range),
                        ['majorDimension' => $major]
                    )->throw()->json()
                );
            }),
            'append_values' => $this->requireId($spreadsheetId, function () use ($request, $spreadsheetId, $range, $values, $inputOption) {
                if ($range === '' || $values === []) {
                    return ConnectorResult::failure('range and values are required');
                }
                $url = 'https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($spreadsheetId).'/values/'.rawurlencode($range).':append?'
                    .http_build_query(['valueInputOption' => $inputOption, 'insertDataOption' => 'INSERT_ROWS']);

                return ConnectorResult::success($request->post($url, ['values' => $values])->throw()->json());
            }),
            'update_values' => $this->requireId($spreadsheetId, function () use ($request, $spreadsheetId, $range, $values, $inputOption) {
                if ($range === '' || $values === []) {
                    return ConnectorResult::failure('range and values are required');
                }
                $url = 'https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($spreadsheetId).'/values/'.rawurlencode($range).'?'
                    .http_build_query(['valueInputOption' => $inputOption]);

                return ConnectorResult::success($request->put($url, ['values' => $values])->throw()->json());
            }),
            'clear_values' => $this->requireId($spreadsheetId, function () use ($request, $spreadsheetId, $range) {
                if ($range === '') {
                    return ConnectorResult::failure('range is required');
                }

                return ConnectorResult::success(
                    $request->post(
                        'https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($spreadsheetId).'/values/'.rawurlencode($range).':clear',
                        new \stdClass
                    )->throw()->json()
                );
            }),
            'create_spreadsheet' => ConnectorResult::success(
                $request->post('https://sheets.googleapis.com/v4/spreadsheets', [
                    'properties' => ['title' => trim((string) ($arguments['title'] ?? 'Enverif Sheet')) ?: 'Enverif Sheet'],
                    'sheets' => [[
                        'properties' => [
                            'title' => trim((string) ($arguments['sheet_title'] ?? 'Sheet1')) ?: 'Sheet1',
                        ],
                    ]],
                ])->throw()->json()
            ),
            'list_recent' => ConnectorResult::success(
                $request->get('https://www.googleapis.com/drive/v3/files', [
                    'q' => $this->driveQuery((string) ($arguments['query'] ?? '')),
                    'pageSize' => max(1, min(50, (int) ($arguments['limit'] ?? 20))),
                    'orderBy' => 'viewedByMeTime desc',
                    'fields' => 'files(id,name,modifiedTime,webViewLink)',
                ])->throw()->json()
            ),
            default => ConnectorResult::failure('Unsupported action'),
        };
    }

    private function request(ConnectorConnection $connection)
    {
        return $this->client(['Authorization' => 'Bearer '.$this->tokens->accessToken($connection)]);
    }

    /** @param callable():ConnectorResult $callback */
    private function requireId(string $spreadsheetId, callable $callback): ConnectorResult
    {
        if ($spreadsheetId === '') {
            return ConnectorResult::failure('spreadsheet_id is required (or set a default on the connection).');
        }

        return $callback();
    }

    private function driveQuery(string $query): string
    {
        $base = "mimeType='application/vnd.google-apps.spreadsheet' and trashed=false";
        $query = trim($query);
        if ($query === '') {
            return $base;
        }
        $escaped = str_replace("'", "\\'", $query);

        return $base." and name contains '{$escaped}'";
    }
}
