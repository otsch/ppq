<?php

namespace Otsch\Ppq;

use Otsch\Ppq\Entities\QueueRecord;

class ListCommand
{
    protected int $cellPadding = 2;

    /**
     * @param mixed[] $args
     */
    public static function argsToString(array $args): string
    {
        $argsString = trim(str_replace(PHP_EOL, '', var_export($args, true)));

        if (str_starts_with($argsString, 'array (') && str_ends_with($argsString, ')')) {
            $argsString = '[' . trim(substr($argsString, 7, -1)) . ']';
        }

        if (str_ends_with($argsString, ',]')) {
            $argsString = substr($argsString, 0, -2) . ']';
        }

        return $argsString;
    }

    /**
     * @throws Exceptions\InvalidQueueDriverException
     */
    public function list(): void
    {
        $listTableData = [
            'columns' => [
                'id' => ['longestValue' => 2],
                'status' => ['longestValue' => 7],
                'jobClass' => ['longestValue' => 8],
                'args' => ['longestValue' => 4],
            ],
            'queues' => [],
        ];

        foreach (Ppq::queueNames() as $queueName) {
            $rows = $this->queueRecordsToListTableData(
                Ppq::running($queueName),
                'running',
                $listTableData['columns'],
            );

            $rows = array_merge($rows, $this->queueRecordsToListTableData(
                Ppq::waiting($queueName),
                'waiting',
                $listTableData['columns'],
            ));

            $listTableData['queues'][$queueName] = $rows;
        }

        $this->printData($listTableData);
    }

    /**
     * @param QueueRecord[] $records
     * @param array<string, array<string, int>> $columns
     * @return mixed[]
     */
    protected function queueRecordsToListTableData(array $records, string $status, array &$columns): array
    {
        $data = [];

        foreach ($records as $queueRecord) {
            $row = [
                'id' => $queueRecord->id,
                'status' => $status,
                'jobClass' => $queueRecord->jobClass,
                'args' => self::argsToString($queueRecord->args),
            ];

            $this->checkLongestValues($row, $columns);

            $data[] = $row;
        }

        return $data;
    }

    /**
     * @param mixed[] $data
     */
    protected function printData(array $data): void
    {
        $lineLength = $this->calcLineLength($data);

        foreach ($data['queues'] as $queueName => $rows) {
            echo str_repeat(' ', (int) floor(($lineLength - strlen($queueName)) / 2)) . strtoupper($queueName) .
                PHP_EOL;

            $this->printSeparatorLine($lineLength, '_');

            $this->printHeadlines($data['columns']);

            $this->printSeparatorLine($lineLength);

            $this->printRows($rows, $data['columns']);

            echo PHP_EOL . PHP_EOL;
        }
    }

    /**
     * @param mixed[] $data
     */
    protected function calcLineLength(array $data): int
    {
        $lineLength = 0;

        foreach ($data['columns'] as $columnData) {
            $lineLength += $columnData['longestValue'] + ($this->cellPadding * 2) + 1;
        }

        return $lineLength + 1;
    }

    protected function printSeparatorLine(int $length, string $char = '-'): void
    {
        for ($i = 1; $i <= $length; $i++) {
            echo $char;
        }

        echo PHP_EOL;
    }

    /**
     * @param array<string, array<string, int>> $columns
     * @return void
     */
    protected function printHeadlines(array $columns): void
    {
        foreach ($columns as $columnName => $columnData) {
            $this->printCell($columnName, $columnData['longestValue']);
        }

        echo "|" . PHP_EOL;
    }

    /**
     * @param mixed[] $rows
     * @param array<string, array<string, int>> $columns
     */
    protected function printRows(array $rows, array $columns): void
    {
        foreach ($rows as $row) {
            foreach ($columns as $columnName => $columnData) {
                $this->printCell($row[$columnName] ?? '', $columnData['longestValue']);
            }

            echo "|" . PHP_EOL;
        }
    }

    protected function printCell(string $value, int $longestValue): void
    {
        echo "|" .
            str_repeat(' ', $this->cellPadding) .
            $value .
            str_repeat(' ', $longestValue - strlen($value)) .
            str_repeat(' ', $this->cellPadding);
    }

    /**
     * @param mixed[] $row
     * @param array<string, array<string, int>> $columns
     * @return void
     */
    protected function checkLongestValues(array $row, array &$columns): void
    {
        foreach ($row as $column => $value) {
            if (isset($columns[$column])) {
                $length = strlen((string) $value);

                if ($length > $columns[$column]['longestValue']) {
                    $columns[$column]['longestValue'] = $length;
                }
            }
        }
    }
}
