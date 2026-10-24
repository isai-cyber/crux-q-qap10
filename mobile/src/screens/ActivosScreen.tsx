import React, { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  RefreshControl,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import Screen from '../components/Screen';
import EmptyState from '../components/EmptyState';
import { activos as activosApi } from '../api/endpoints';
import type { Activo } from '../api/types';
import { colors, font, radius, spacing } from '../theme/tokens';
import type { RootStackParamList } from '../navigation/RootNavigator';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';

type Props = {
  navigation: NativeStackNavigationProp<RootStackParamList, 'Activos'>;
};

const estadoColorActivo: Record<string, string> = {
  operativo: colors.emerald,
  mantenimiento: colors.amber,
  fuera_servicio: colors.rose,
};

export default function ActivosScreen({ navigation }: Props) {
  const [q, setQ] = useState('');
  const [items, setItems] = useState<Activo[]>([]);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const cargar = useCallback(async (busqueda: string) => {
    setError(null);
    setCargando(true);
    try {
      const respuesta = await activosApi(busqueda.trim() || undefined);
      setItems(respuesta.items);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Error al cargar');
    } finally {
      setCargando(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      cargar('');
    }, [cargar])
  );

  return (
    <Screen titulo="Activos" subtitulo="Inventario técnico de la planta">
      <View style={styles.busquedaFila}>
        <TextInput
          style={styles.busqueda}
          value={q}
          onChangeText={setQ}
          placeholder="Buscar por nombre…"
          placeholderTextColor={colors.textLight}
          returnKeyType="search"
          onSubmitEditing={() => cargar(q)}
        />
        <TouchableOpacity style={styles.botonBuscar} onPress={() => cargar(q)}>
          <Text style={styles.botonBuscarTexto}>🔍</Text>
        </TouchableOpacity>
      </View>

      {cargando ? (
        <ActivityIndicator color={colors.blue} style={styles.cargando} />
      ) : error ? (
        <Text style={styles.error}>{error}</Text>
      ) : (
        <FlatList
          data={items}
          keyExtractor={(a) => String(a.id)}
          refreshControl={
            <RefreshControl
              refreshing={cargando}
              onRefresh={() => cargar(q)}
              tintColor={colors.blue}
            />
          }
          renderItem={({ item }) => (
            <TouchableOpacity
              style={styles.fila}
              onPress={() => navigation.navigate('ActivoDetalle', { id: item.id })}
            >
              <View style={styles.filaInfo}>
                <Text style={styles.nombre}>{item.nombre}</Text>
                <Text style={styles.tipo}>
                  {item.tipo ?? 'Sin tipo'}
                  {item.ubicacion ? ` · ${item.ubicacion}` : ''}
                </Text>
              </View>
              <View
                style={[
                  styles.punto,
                  { backgroundColor: estadoColorActivo[item.estado] ?? colors.textSoft },
                ]}
              />
            </TouchableOpacity>
          )}
          ListEmptyComponent={<EmptyState mensaje="Sin activos que coincidan." />}
        />
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  busquedaFila: { flexDirection: 'row', gap: spacing.sm },
  busqueda: {
    flex: 1,
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: radius.md,
    paddingHorizontal: spacing.lg,
    paddingVertical: 10,
    fontSize: 14,
    color: colors.text,
  },
  botonBuscar: {
    backgroundColor: colors.navy,
    borderRadius: radius.md,
    width: 44,
    alignItems: 'center',
    justifyContent: 'center',
  },
  botonBuscarTexto: { fontSize: 16 },
  cargando: { marginTop: spacing.xxl },
  error: { color: colors.rose, textAlign: 'center', marginTop: spacing.xl },
  fila: {
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    padding: spacing.lg,
    marginBottom: spacing.md,
    flexDirection: 'row',
    alignItems: 'center',
    shadowColor: colors.text,
    shadowOpacity: 0.04,
    shadowRadius: 6,
    shadowOffset: { width: 0, height: 1 },
    elevation: 1,
  },
  filaInfo: { flex: 1, gap: 2 },
  nombre: { fontSize: 15, fontWeight: '700', color: colors.text, fontFamily: font.semiBold },
  tipo: { fontSize: 12, color: colors.textSoft },
  punto: { width: 10, height: 10, borderRadius: 5 },
});
