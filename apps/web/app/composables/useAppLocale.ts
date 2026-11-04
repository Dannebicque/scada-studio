export const messages = {
  fr: {
    documentation: 'Documentation', home: 'Accueil', catalog: 'Catalogue', dashboard: 'Tableaux de bord', synoptic: 'Synoptiques', connectors: 'Connecteurs',
    language: 'Langue', navigation: 'Navigation principale', skip: 'Aller au contenu', workspace: 'Espace de supervision',
    overview: 'Vue d’ensemble', introduction: 'Supervision des équipements et des mesures en temps réel.',
    backend: 'État du backend', checking: 'Vérification de l’API…', healthy: 'API opérationnelle',
    unavailable: 'API indisponible', refresh: 'Actualiser', coming: 'Fonctionnalité prévue dans un prochain lot.',
    dashboardIntro: 'L’éditeur de tableaux de bord persistants arrive dans le lot L3.',
    synopticIntro: 'L’éditeur de synoptiques SVG arrive dans le lot L4.',
    connectorsIntro: 'Sources de données, protocoles et paramètres de connexion.',
    mqtt: 'MQTT', mqttDescription: 'Abonnement aux topics de télémétrie en lecture seule.',
    mqttTopic: 'Format des topics', mqttPort: 'Port local de développement', mqttStatus: 'Statut',
    available: 'Disponible en développement', planned: 'Prévu', opcua: 'OPC UA',
    opcuaDescription: 'Connecteur industriel prévu dans un lot ultérieur.',
    security: 'Sécurité', securityText: 'Le broker MQTT de démonstration est anonyme et limité à localhost. Ne pas l’exposer sur Internet.',
    configuration: 'Configuration', configurationText: 'Le paramétrage des connecteurs dans l’interface est prévu pour L3.2. Pour le moment, la configuration MQTT est locale.',
    details: 'Documentation technique',
    dashboardEmpty: 'Aucun tableau de bord disponible. Exécutez app:demo:catalog après les migrations.',
    catalogTitle: 'Catalogue de supervision', catalogIntro: 'Espaces, sources et variables en lecture seule. Rafraîchissement des mesures toutes les 5 secondes ; péremption après 30 secondes.',
    workspaces: 'Espaces', sources: 'Sources', tags: 'Variables', name: 'Nom', key: 'Clé', type: 'Type', unit: 'Unité', access: 'Accès', lastValue: 'Dernière valeur', timestamp: 'Horodatage', quality: 'Qualité',
    readOnly: 'Lecture seule', declaredWrite: 'Écriture déclarée', active: 'Active', inactive: 'Inactive', valid: 'Valide', stale: 'Périmée', noReading: 'Aucune mesure', invalidTimestamp: 'Horodatage invalide',
    catalogUnavailable: 'Catalogue indisponible. Vérifiez Symfony et PostgreSQL.', telemetryUnavailable: 'Valeurs de télémétrie indisponibles.', loading: 'Chargement…', loadingTelemetry: 'Chargement des mesures…',
    moveEarlier: 'Déplacer vers le début', moveLater: 'Déplacer vers la fin',
    saveLocal: 'Enregistrer sur cet appareil', restoreServer: 'Restaurer le dashboard serveur', localLayout: 'Disposition locale enregistrée sur cet appareil (non partagée).', localSaved: 'Disposition enregistrée localement.', localRestored: 'Disposition serveur restaurée.', invalidLocal: 'Disposition locale invalide ignorée.',
    editPreview: 'Prévisualiser une disposition', addWidget: 'Ajouter un indicateur', removeWidget: 'Retirer', resetPreview: 'Annuler les modifications', previewWarning: 'Prévisualisation locale : les modifications ne sont pas enregistrées.', chooseTag: 'Choisir une variable',
    dashboardError: 'Impossible de charger les tableaux de bord.', dashboardLive: 'Dernières mesures', dashboardNoValue: 'En attente de mesure', dashboardReadOnly: 'Vue en lecture seule — édition prévue dans L3.4',
    systemStatus: 'État du système', apiOnline: 'API accessible', apiOffline: 'API indisponible', apiChecking: 'API : vérification',
    telemetryFresh: 'Mesures récentes', telemetryStale: 'Mesures périmées', telemetryEmpty: 'Aucune mesure', telemetryUnknown: 'Télémétrie indisponible',
    lastChecked: 'Dernière vérification', systemStatusNote: 'La disponibilité du broker MQTT n’est pas mesurée directement.',
  },
  en: {
    documentation: 'Documentation', home: 'Home', catalog: 'Catalog', dashboard: 'Dashboards', synoptic: 'Synoptics', connectors: 'Connectors',
    language: 'Language', navigation: 'Main navigation', skip: 'Skip to content', workspace: 'Monitoring workspace',
    overview: 'Overview', introduction: 'Real-time equipment and telemetry monitoring.',
    backend: 'Backend status', checking: 'Checking API…', healthy: 'API operational',
    unavailable: 'API unavailable', refresh: 'Refresh', coming: 'This feature is planned for a future milestone.',
    dashboardIntro: 'The persistent dashboard editor is coming in L3.',
    synopticIntro: 'The SVG synoptic editor is coming in L4.',
    connectorsIntro: 'Data sources, protocols, and connection settings.',
    mqtt: 'MQTT', mqttDescription: 'Read-only telemetry topic subscription.',
    mqttTopic: 'Topic format', mqttPort: 'Local development port', mqttStatus: 'Status',
    available: 'Available in development', planned: 'Planned', opcua: 'OPC UA',
    opcuaDescription: 'Industrial connector planned for a future milestone.',
    security: 'Security', securityText: 'The demo MQTT broker is anonymous and bound to localhost. Do not expose it to the Internet.',
    configuration: 'Configuration', configurationText: 'Connector settings in the UI are planned for L3.2. MQTT configuration is currently local.',
    details: 'Technical documentation',
    dashboardEmpty: 'No dashboards available. Run app:demo:catalog after migrations.',
    catalogTitle: 'Monitoring catalog', catalogIntro: 'Read-only workspaces, sources, and tags. Readings refresh every 5 seconds and become stale after 30 seconds.',
    workspaces: 'Workspaces', sources: 'Sources', tags: 'Tags', name: 'Name', key: 'Key', type: 'Type', unit: 'Unit', access: 'Access', lastValue: 'Latest value', timestamp: 'Timestamp', quality: 'Quality',
    readOnly: 'Read-only', declaredWrite: 'Write declared', active: 'Active', inactive: 'Inactive', valid: 'Valid', stale: 'Stale', noReading: 'No reading', invalidTimestamp: 'Invalid timestamp',
    catalogUnavailable: 'Catalog unavailable. Check Symfony and PostgreSQL.', telemetryUnavailable: 'Telemetry readings unavailable.', loading: 'Loading…', loadingTelemetry: 'Loading readings…',
    moveEarlier: 'Move earlier', moveLater: 'Move later',
    saveLocal: 'Save on this device', restoreServer: 'Restore server dashboard', localLayout: 'Layout saved on this device (not shared).', localSaved: 'Layout saved locally.', localRestored: 'Server layout restored.', invalidLocal: 'Invalid local layout ignored.',
    editPreview: 'Preview a layout', addWidget: 'Add value widget', removeWidget: 'Remove', resetPreview: 'Discard changes', previewWarning: 'Local preview: changes are not saved.', chooseTag: 'Choose a tag',
    dashboardError: 'Unable to load dashboards.', dashboardLive: 'Latest readings', dashboardNoValue: 'Waiting for a reading', dashboardReadOnly: 'Read-only view — editing planned for L3.4',
    systemStatus: 'System status', apiOnline: 'API reachable', apiOffline: 'API unavailable', apiChecking: 'API: checking',
    telemetryFresh: 'Recent readings', telemetryStale: 'Stale readings', telemetryEmpty: 'No readings', telemetryUnknown: 'Telemetry unavailable',
    lastChecked: 'Last checked', systemStatusNote: 'MQTT broker availability is not monitored directly.',
  },
} as const

export type Locale = keyof typeof messages
export type TranslationKey = keyof typeof messages.fr

export function useAppLocale() {
  const locale = useState<Locale>('scada-locale', () => 'fr')
  const t = (key: TranslationKey): string => messages[locale.value][key]
  const setLocale = (value: Locale) => {
    locale.value = value
    if (import.meta.client) {
      localStorage.setItem('scada-locale', value)
      document.documentElement.lang = value
    }
  }
  onMounted(() => {
    const stored = localStorage.getItem('scada-locale')
    setLocale(stored === 'en' ? 'en' : 'fr')
  })
  return { locale, t, setLocale }
}
