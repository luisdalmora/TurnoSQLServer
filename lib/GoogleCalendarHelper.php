<?php
// lib/GoogleCalendarHelper.php (Simplificado para apenas listar eventos de calendários públicos)

// Caminhos atualizados
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php'; // Para GOOGLE_APPLICATION_NAME e GOOGLE_API_KEY

use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleServiceCalendar;

class GoogleCalendarHelper {
    private $client;
    private $logger;

    public function __construct(LogHelper $logger) {
        $this->logger = $logger;

        $this->client = new GoogleClient();
        $this->client->setApplicationName(GOOGLE_APPLICATION_NAME); // Constante de config.php

        if (defined('GOOGLE_API_KEY') && !empty(GOOGLE_API_KEY)) {
            $this->client->setDeveloperKey(GOOGLE_API_KEY); // Constante de config.php
        } else {
            $this->logger->log('GCAL_WARNING', 'GOOGLE_API_KEY não definida ou vazia em config.php. Acesso a calendários públicos pode falhar.', []);
        }
    }

    public function listEventsFromCalendar($calendarId, $optParams = []) {
        $service = new GoogleServiceCalendar($this->client);

        try {
            if (!$this->client instanceof GoogleClient) {
                $this->logger->log('GCAL_CRITICAL', 'Google Client não inicializado corretamente em listEventsFromCalendar.');
                return null;
            }
            $events = $service->events->listEvents($calendarId, $optParams);
            return $events->getItems();
        } catch (\Google\Service\Exception $e) { 
            $this->logger->log('GCAL_ERROR', 'Google Service Exception ao LISTAR eventos de ' . $calendarId . ': ' . $e->getMessage(), [
                'calendar_id' => $calendarId, 'errors' => $e->getErrors()
            ]);
            return null;
        } catch (\Google\Exception $e) { 
             $this->logger->log('GCAL_ERROR', 'Google Library Exception ao LISTAR eventos de ' . $calendarId . ': ' . $e->getMessage(), [
                'calendar_id' => $calendarId
            ]);
            return null;
        } catch (Exception $e) { 
            $this->logger->log('GCAL_ERROR', 'Erro genérico ao LISTAR eventos de ' . $calendarId . ': ' . $e->getMessage(), [
                'calendar_id' => $calendarId, 'trace' => $e->getTraceAsString() // Cuidado com trace em produção
            ]);
            return null;
        }
    }
}
