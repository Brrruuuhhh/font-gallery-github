<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin/bootstrap.php';

try {
    adminDispatch();
} catch (ApiException $error) {
    adminErrorResponse($error);
} catch (Throwable $error) {
    // Сообщения исключений могут содержать запросы к БД и данные для входа; записываем только тип и код ошибки.
    error_log('Font Gallery API: ' . get_class($error) . ' [' . $error->getCode() . '] at '
        . basename($error->getFile()) . ':' . $error->getLine());
    adminErrorResponse(new ApiException(500, 'INTERNAL_ERROR', 'Не удалось выполнить запрос. Повторите попытку позднее.'));
}
