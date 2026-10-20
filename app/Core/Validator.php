<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;

/**
 * Validación de datos en el servidor.
 *
 * Reglas disponibles (separadas por |):
 *   required, nullable, string, integer, numeric, decimal[:n], boolean,
 *   min:n, max:n, email, date[:Y-m-d], in:a,b,c, regex:/patron/,
 *   same:campo, unique:tabla,columna[,idExcluido], exists:tabla,columna
 *
 * Ejemplo:
 *   Validator::make($datos, ['nombre' => 'required|string|max:120'])
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $validated = [];

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     */
    private function __construct(
        private array $data,
        private array $rules,
        private array $labels
    ) {
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     */
    public static function make(array $data, array $rules, array $labels = []): self
    {
        $validator = new self($data, $rules, $labels);
        $validator->run();

        return $validator;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $message) {
            return $message;
        }

        return null;
    }

    /** @return array<string, mixed> Datos que pasaron la validación. */
    public function validated(): array
    {
        return $this->validated;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            $rules = explode('|', $ruleString);
            $hasValue = $value !== null && $value !== '';

            if (in_array('nullable', $rules, true) && !$hasValue) {
                $this->validated[$field] = null;
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === '' || $rule === 'nullable') {
                    continue;
                }

                if ($this->applyRule($field, $value, $rule, $hasValue)) {
                    // El campo ya no es válido: se pasa a la siguiente regla del
                    // conjunto para no generar mensajes contradictorios.
                    continue;
                }

                break;
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }
    }

    private function applyRule(string $field, mixed $value, string $rule, bool $hasValue): bool
    {
        [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);
        $label = $this->labels[$field] ?? $this->humanize($field);

        switch ($name) {
            case 'required':
                if (!$hasValue) {
                    $this->addError($field, sprintf('El campo %s es obligatorio.', $label));
                    return false;
                }
                return true;

            case 'string':
                if ($hasValue && !is_scalar($value)) {
                    $this->addError($field, sprintf('El campo %s debe ser texto.', $label));
                    return false;
                }
                return true;

            case 'integer':
                if ($hasValue && filter_var((string) $value, FILTER_VALIDATE_INT) === false) {
                    $this->addError($field, sprintf('El campo %s debe ser un número entero.', $label));
                    return false;
                }
                return true;

            case 'numeric':
                if ($hasValue && !is_numeric((string) $value)) {
                    $this->addError($field, sprintf('El campo %s debe ser un número.', $label));
                    return false;
                }
                return true;

            case 'decimal':
                if ($hasValue && !$this->isDecimal((string) $value, $parameter === null ? 2 : (int) $parameter)) {
                    $this->addError($field, sprintf('El campo %s debe ser un importe válido.', $label));
                    return false;
                }
                return true;

            case 'boolean':
                if ($hasValue && !in_array(strtolower((string) $value), ['0', '1', 'true', 'false', 'on', 'off', 'yes', 'no'], true)) {
                    $this->addError($field, sprintf('El campo %s debe ser verdadero o falso.', $label));
                    return false;
                }
                return true;

            case 'min':
                $limit = (int) $parameter;

                if ($hasValue && mb_strlen((string) $value) < $limit) {
                    $this->addError($field, sprintf('El campo %s debe tener al menos %d caracteres.', $label, $limit));
                    return false;
                }
                return true;

            case 'max':
                $limit = (int) $parameter;

                if ($hasValue && mb_strlen((string) $value) > $limit) {
                    $this->addError($field, sprintf('El campo %s no debe superar %d caracteres.', $label, $limit));
                    return false;
                }
                return true;

            case 'email':
                if ($hasValue && filter_var((string) $value, FILTER_VALIDATE_EMAIL) === false) {
                    $this->addError($field, sprintf('El campo %s debe ser un correo electrónico válido.', $label));
                    return false;
                }
                return true;
            case 'url':
                if ($hasValue && filter_var((string) $value, FILTER_VALIDATE_URL) === false) {
                    $this->addError($field, sprintf('El campo %s debe ser un enlace valido (http o https).', $label));
                    return false;
                }
                return true;


            case 'date':
                if ($hasValue && !$this->isDate((string) $value, $parameter)) {                    $this->addError($field, sprintf('El campo %s debe ser una fecha válida.', $label));
                    return false;
                }
                return true;

            case 'in':
                $allowed = array_map('trim', explode(',', (string) $parameter));

                if ($hasValue && !in_array((string) $value, $allowed, true)) {
                    $this->addError($field, sprintf('El valor seleccionado en %s no es válido.', $label));
                    return false;
                }
                return true;

            case 'regex':
                if ($hasValue && $parameter !== null && @preg_match($parameter, (string) $value) !== 1) {
                    $this->addError($field, sprintf('El formato del campo %s no es válido.', $label));
                    return false;
                }
                return true;

            case 'same':
                $other = $parameter === null ? '' : $parameter;

                if ($hasValue && (string) $value !== (string) ($this->data[$other] ?? '')) {
                    $this->addError($field, sprintf('El campo %s no coincide con %s.', $label, $this->labels[$other] ?? $this->humanize($other)));
                    return false;
                }
                return true;

            case 'unique':
                if (!$hasValue) {
                    return true;
                }

                if ($this->existsInDatabase((string) $parameter, (string) $value)) {
                    $this->addError($field, sprintf('El valor ingresado en %s ya está registrado.', $label));
                    return false;
                }
                return true;

            case 'exists':
                if (!$hasValue) {
                    return true;
                }

                if (!$this->existsInDatabase((string) $parameter, (string) $value)) {
                    $this->addError($field, sprintf('El valor seleccionado en %s no existe.', $label));
                    return false;
                }
                return true;

            default:
                return true;
        }
    }

    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }

    private function isDecimal(string $value, int $decimals): bool
    {
        $pattern = $decimals > 0
            ? '/^-?\d{1,12}(\.\d{1,' . $decimals . '})?$/'
            : '/^-?\d{1,12}$/';

        return preg_match($pattern, $value) === 1;
    }

    private function isDate(string $value, ?string $format): bool
    {
        $format = $format === null || $format === '' ? 'Y-m-d' : $format;
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);

        return $date instanceof DateTimeImmutable && $date->format($format) === $value;
    }

    /**
     * Comprueba la existencia o unicidad de un valor en la base de datos.
     *
     * Parámetro esperado: tabla,columna[,idExcluido]
     */
    private function existsInDatabase(string $parameter, string $value): bool
    {
        $parts = array_map('trim', explode(',', $parameter));

        if (count($parts) < 2) {
            return false;
        }

        $prefix = (string) Config::get('database.prefix', '');
        $table = $parts[0];
        $column = $parts[1];
        $excludeId = (int) ($parts[2] ?? 0);

        if (!str_starts_with($table, $prefix)) {
            $table = $prefix . $table;
        }

        if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1 || preg_match('/^[A-Za-z0-9_]+$/', $column) !== 1) {
            return false;
        }

        try {
            $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE `%s` = :valor', $table, $column);
            $params = ['valor' => $value];

            if ($excludeId > 0) {
                $sql .= ' AND id <> :excluido';
                $params['excluido'] = $excludeId;
            }

            $statement = Database::connection()->prepare($sql);
            $statement->execute($params);

            return (int) $statement->fetchColumn() > 0;
        } catch (\Throwable $exception) {
            Logger::error('Error al validar unicidad/existencia', [
                'table' => $table,
                'column' => $column,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function humanize(string $field): string
    {
        return str_replace('_', ' ', $field);
    }
}
