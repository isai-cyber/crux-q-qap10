import React from 'react';
import { ActivityIndicator, View, StyleSheet } from 'react-native';
import { NavigationContainer, DefaultTheme } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { useAuth } from '../store/AuthContext';
import { colors, font } from '../theme/tokens';

import LoginScreen from '../screens/LoginScreen';
import DashboardScreen from '../screens/DashboardScreen';
import TicketsScreen from '../screens/TicketsScreen';
import TicketDetailScreen from '../screens/TicketDetailScreen';
import ActivosScreen from '../screens/ActivosScreen';
import ActivoDetailScreen from '../screens/ActivoDetailScreen';
import ChatScreen from '../screens/ChatScreen';

export type RootStackParamList = {
  Login: undefined;
  Dashboard: undefined;
  Tickets: undefined;
  TicketDetalle: { id: number };
  Activos: undefined;
  ActivoDetalle: { id: number };
  Chat: undefined;
};

const Stack = createNativeStackNavigator<RootStackParamList>();

const temaNavegacion = {
  ...DefaultTheme,
  colors: {
    ...DefaultTheme.colors,
    background: colors.bg,
    primary: colors.blue,
  },
};

const opcionesPantalla = {
  headerShown: false,
  contentStyle: { backgroundColor: colors.bg },
};

export default function RootNavigator() {
  const { listo, sesion } = useAuth();

  if (!listo) {
    return (
      <View style={styles.cargando}>
        <ActivityIndicator color={colors.blue} size="large" />
      </View>
    );
  }

  return (
    <NavigationContainer theme={temaNavegacion}>
      <Stack.Navigator screenOptions={opcionesPantalla}>
        {sesion ? (
          <>
            <Stack.Screen name="Dashboard" component={DashboardScreen} />
            <Stack.Screen name="Tickets" component={TicketsScreen} />
            <Stack.Screen name="TicketDetalle" component={TicketDetailScreen} />
            <Stack.Screen name="Activos" component={ActivosScreen} />
            <Stack.Screen name="ActivoDetalle" component={ActivoDetailScreen} />
            <Stack.Screen name="Chat" component={ChatScreen} />
          </>
        ) : (
          <Stack.Screen name="Login" component={LoginScreen} />
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
}

const styles = StyleSheet.create({
  cargando: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.bg },
});
