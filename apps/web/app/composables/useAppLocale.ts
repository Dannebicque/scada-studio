export const messages = {
  fr: {
    home: 'Accueil', catalog: 'Catalogue', dashboard: 'Tableaux de bord', synoptic: 'Synoptiques', connectors: 'Connecteurs',
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
    moveEarlier: 'Déplacer vers le début', moveLater: 'Déplacer vers la fin',
    editPreview: 'Prévisualiser une disposition', addWidget: 'Ajouter un indicateur', removeWidget: 'Retirer', resetPreview: 'Annuler les modifications', previewWarning: 'Prévisualisation locale : les modifications ne sont pas enregistrées.', chooseTag: 'Choisir une variable',
    dashboardError: 'Impossible de charger les tableaux de bord.', dashboardLive: 'Dernières mesures', dashboardNoValue: 'En attente de mesure', dashboardReadOnly: 'Vue en lecture seule — édition prévue dans L3.4',
    systemStatus: 'État du système', apiOnline: 'API accessible', apiOffline: 'API indisponible', apiChecking: 'API : vérification',
    telemetryFresh: 'Mesures récentes', telemetryStale: 'Mesures périmées', telemetryEmpty: 'Aucune mesure', telemetryUnknown: 'Télémétrie indisponible',
    lastChecked: 'Dernière vérification', systemStatusNote: 'La disponibilité du broker MQTT n’est pas mesurée directement.',
  },
  en: {
    home: 'Home', catalog: 'Catalog', dashboard: 'Dashboards', synoptic: 'Synoptics', connectors: 'Connectors',
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
    moveEarlier: 'Move earlier', moveLater: 'Move later',
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
