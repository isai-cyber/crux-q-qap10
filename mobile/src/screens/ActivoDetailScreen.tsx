import React, { useCallback, useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import Screen from '../components/Screen';
import { activo as activoApi } from '../api/endpoints';
import { colors, font, radius, spacing } from '../theme/tokens';

type Detalle = Awaited<ReturnType<typeof activoApi>>;

export default function ActivoDetailScreen({ route }: { route: { params: { id: number } } }) {
  const { id } = route.params;
  const [activo, setActivo] = useState<Detalle | null>(null);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      (async () => {
        try {
          setError(null);
          setActivo(await activoApi(id));
        } catch (e) {
          setError(e instanceof Error ? e.message : 'Error al cargar');
        } finally {
          setCargando(false);
        }
      })();
    }, [id])
  );

  if (cargando) {
    return (
      <Screen titulo="Activo">
        <ActivityIndicator color={colors.blue} style={styles.cargando} />
      </Screen>
    );
  }

  return (
    <Screen titulo={activo?.nombre ?? 'Activo'} subtitulo={activo?.planta_nombre ?? undefined}>
      {error ? <Text style={styles.error}>{error}</Text> : null}
      {activo ? (
        <ScrollView showsVerticalScrollIndicator={false}>
          <View style={styles.card}>
            <Text style={styles.seccion}>Ficha técnica</Text>
            <Fila etiqueta="Tipo" valor={activo.tipo ?? '—'} />
            <Fila etiqueta="Ubicación" valor={activo.ubicacion ?? '—'} />
            <Fila etiqueta="IP" valor={activo.ip_address ?? '—'} mono />
            <Fila etiqueta="Estado" valor={activo.estado} />
          </View>

          <Text style={styles.seccionHistorial}>Historial de diagnósticos</Text>
          {activo.diagnosticos.length === 0 ? (
            <Text style={styles.vacio}>Sin diagnósticos registrados.</Text>
          ) : (
            activo.diagnosticos.map((d) => (
              <View key={d.id} style={styles.card}>
                <Text style={styles.texto}>{d.descripcion}</Text>
                <Text style={styles.fecha}>{d.created_at}</Text>
              </View>
            ))
          )}
        </ScrollView>
      ) : null}
    </Screen>
  );
}

function Fila({ etiqueta, valor, mono }: { etiqueta: string; valor: string; mono?: boolean }) {
  return (
    <View style={styles.fila}>
      <Text style={styles.etiqueta}>{etiqueta}</Text>
      <Text style={[styles.valor, mono && { fontFamily: font.mono }]}>{valor}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  cargando: { marginTop: spacing.xxl },
  error: {
    color: colors.rose,
    backgroundColor: 'rgba(225,29,72,0.08)',
    borderColor: 'rgba(225,29,72,0.25)',
    borderWidth: 1,
    borderRadius: radius.sm,
    padding: spacing.md,
  },
  card: {
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    padding: spacing.lg,
    gap: spacing.sm,
    marginBottom: spacing.md,
    shadowColor: colors.text,
    shadowOpacity: 0.05,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 2 },
    elevation: 1,
  },
  fila: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 4,
  },
  etiqueta: { fontSize: 13, color: colors.textSoft, fontWeight: '600' },
  valor: { fontSize: 13, color: colors.text, textAlign: 'right', flexShrink: 1 },
  texto: { fontSize: 14, color: colors.text, lineHeight: 20 },
  fecha: { fontSize: 11, color: colors.textLight, fontFamily: font.mono },
  seccion: {
    fontSize: 11,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    color: colors.textSoft,
  },
  seccionHistorial: {
    fontSize: 15,
    fontWeight: '700',
    color: colors.text,
    fontFamily: font.bold,
    marginBottom: spacing.md,
  },
  vacio: { color: colors.textSoft, fontSize: 13 },
});
