export const environment = {
    production: true, // Se vuelve true al buildear por defecto
    apiAuth: {
        url: 'http://localhost:8004', // Reemplazar en PRD
        grantType: 'client_credentials',
        clientId: 'TU_CLIENT_ID',
        clientSecret: 'TU_CLIENT_SECRET'
    },
    sessionInactivityMinutes: 30
};
