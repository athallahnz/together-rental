<?php

namespace Tests\Unit;

use App\Domain\LegacyImport\RentalV1Normalizer;
use App\Domain\LegacyImport\RentalV1SqlParser;
use App\Domain\LegacyImport\RentalV1Value;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RentalV1SqlParserTest extends TestCase
{
    #[Test]
    public function it_parses_allowlisted_insert_rows_without_executing_sql(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rental-v1-parser-');
        $sql = <<<'SQL'
DROP TABLE IF EXISTS `customer`;
CREATE TABLE `customer` (
  `customer_id` int(11) NOT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_nohp` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`customer_id`)
) ENGINE=InnoDB;
INSERT INTO `customer` VALUES (1,'ALFIAN MU\'ARIF','08123'),(2,'Dwi ''Putri''',NULL);
CREATE TABLE `not_allowlisted` (
  `id` int(11) NOT NULL
) ENGINE=InnoDB;
INSERT INTO `not_allowlisted` VALUES (99);
SQL;
        file_put_contents($path, $sql);

        $rows = [];
        $result = (new RentalV1SqlParser)->parse(
            $path,
            static function (string $table, int $rowNumber, array $payload) use (&$rows): void {
                $rows[] = compact('table', 'rowNumber', 'payload');
            },
        );

        @unlink($path);

        $this->assertCount(2, $rows);
        $this->assertSame("ALFIAN MU'ARIF", $rows[0]['payload']['customer_name']);
        $this->assertSame("Dwi 'Putri'", $rows[1]['payload']['customer_name']);
        $this->assertNull($rows[1]['payload']['customer_nohp']);
        $this->assertSame(2, $result['tables']['customer']['rows']);
        $this->assertContains('not_allowlisted', $result['ignored_tables']);
    }

    #[Test]
    public function it_removes_legacy_passwords_and_tokens_before_staging(): void
    {
        $payload = [
            'iduser' => '1',
            'uname' => 'operator',
            'pwd' => 'legacy-md5-hash',
            'api_token' => 'legacy-token',
        ];

        $sanitized = (new RentalV1Normalizer)->sanitize('user', $payload);

        $this->assertArrayNotHasKey('pwd', $sanitized);
        $this->assertArrayNotHasKey('api_token', $sanitized);
        $this->assertSame('operator', $sanitized['uname']);
    }

    #[Test]
    public function it_rejects_ole_sentinel_dates_only_for_operational_datetimes(): void
    {
        $this->assertNull(RentalV1Value::operationalDateTime('1899-12-30 00:39:33'));
        $this->assertSame(
            '2025-11-29 12:43:28',
            RentalV1Value::operationalDateTime('2025-11-29 12:43:28'),
        );
        $this->assertSame('1934-09-09', RentalV1Value::date('1934-09-09'));
    }
}
