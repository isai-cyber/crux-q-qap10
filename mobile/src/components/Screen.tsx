import React from 'react';
import {
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { colors, font, spacing } from '../theme/tokens';

type Props = {
  titulo: string;
  subtitulo?: string;
  scroll?: boolean;
  children: React.ReactNode;
};

/** Contenedor común: encabezado navy con el título de la pantalla. */
export default function Screen({ titulo, subtitulo, scroll = true, children }: Props) {
  const cuerpo = scroll ? (
    <ScrollView
      contentContainerStyle={styles.contenido}
      keyboardShouldPersistTaps="handled"
    >
      {children}
    </ScrollView>
  ) : (
    <View style={styles.contenido}>{children}</View>
  );

  return (
    <KeyboardAvoidingView
      style={styles.flex}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <View style={styles.encabezado}>
        <Text style={styles.titulo}>{titulo}</Text>
        {subtitulo ? <Text style={styles.subtitulo}>{subtitulo}</Text> : null}
      </View>
      {cuerpo}
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: colors.bg },
  encabezado: {
    backgroundColor: colors.navy,
    paddingHorizontal: spacing.xl,
    paddingTop: spacing.xl,
    paddingBottom: spacing.lg,
    borderBottomLeftRadius: 20,
    borderBottomRightRadius: 20,
  },
  titulo: {
    fontSize: 22,
    fontWeight: '700',
    color: colors.white,
    fontFamily: font.extraBold,
    letterSpacing: -0.4,
  },
  subtitulo: {
    fontSize: 13,
    color: '#CBD5E1',
    marginTop: 2,
  },
  contenido: {
    padding: spacing.xl,
    gap: spacing.lg,
    flexGrow: 1,
  },
});
