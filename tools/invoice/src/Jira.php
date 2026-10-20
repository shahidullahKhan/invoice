<?php
namespace InvoiceTool;

/** Minimal Jira Cloud client: looks up the "Application Code" (bill code) of many tickets at once. */
class Jira
{
    /** @var string */
    private $base;
    /** @var string */
    private $auth;

    public function __construct(string $base, string $email, string $token)
    {
        $this->base = rtrim($base, '/');
        $this->auth = base64_encode($email . ':' . $token);
    }

    /**
     * @param string[] $keys Ticket keys, e.g. ['WR-11692', 'CRA-13021']
     * @return array<string, string> key → bill code ('' when the field is empty or the ticket is not visible)
     */
    public function billCodes(array $keys, string $fieldName): array
    {
        $fieldId = $this->fieldId($fieldName);
        $codes   = array_fill_keys($keys, '');

        // Bulk fetch accepts up to 100 keys per request and reports unknown keys instead of failing
        foreach (array_chunk($keys, 100) as $chunk) {
            $res = $this->request('POST', '/rest/api/3/issue/bulkfetch', [
                'issueIdsOrKeys' => $chunk,
                'fields'         => [$fieldId],
            ]);
            foreach ($res['issues'] ?? [] as $issue) {
                if (isset($codes[$issue['key']])) {
                    $codes[$issue['key']] = self::billCode(self::fieldText($issue['fields'][$fieldId] ?? null));
                }
            }
        }
        return $codes;
    }

    /** Verify credentials; returns the Jira display name of the token owner. */
    public function whoAmI(): string
    {
        return $this->request('GET', '/rest/api/3/myself')['displayName'] ?? '';
    }

    /** "Texas Zip Codes / ZIPC020" → "ZIPC020" (plain codes pass through unchanged). */
    private static function billCode(string $value): string
    {
        $pos = strrpos($value, '/');
        return $pos === false ? $value : trim(substr($value, $pos + 1));
    }

    /** Resolve a custom field's display name to its id (customfield_XXXXX). */
    private function fieldId(string $name): string
    {
        foreach ($this->request('GET', '/rest/api/3/field') as $field) {
            if (strcasecmp($field['name'], $name) === 0) {
                return $field['id'];
            }
        }
        throw new \RuntimeException("Jira field \"$name\" was not found.");
    }

    /** Custom fields can be plain text, a select option, or a multi-select; flatten to text. */
    private static function fieldText($value): string
    {
        if (is_array($value)) {
            if (isset($value['value'])) {
                return trim($value['value']);
            }
            return implode(', ', array_filter(array_map([self::class, 'fieldText'], $value)));
        }
        return trim((string) $value);
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . $this->auth,
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('Could not reach Jira: ' . $error);
        }
        if ($status === 401 || $status === 403) {
            throw new \RuntimeException('Jira rejected your credentials. Check the Jira email and API token in Settings.');
        }
        if ($status >= 400) {
            throw new \RuntimeException("Jira request failed (HTTP $status).");
        }
        return json_decode($raw, true) ?: [];
    }
}
