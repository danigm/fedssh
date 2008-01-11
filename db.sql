DROP TABLE IF EXISTS `pubkey`;
CREATE TABLE `pubkey` (
  `uid` varchar(32) NOT NULL,
  `init` datetime NOT NULL,
  `timeout` int(11) NOT NULL,
  `pubkey` varchar(600) NOT NULL,
  PRIMARY KEY  (`uid`)
);

INSERT INTO `pubkey` VALUES ('danigm-us','2007-12-17 12:49:39',300,'ssh-rsa AAAAB3NzaC1yc2EAAAABIwAAAQEA4Okl5atp1iNNzm7VsODjJmi+DT7VZ2d1Vw/tg+j99M6JZpXR9QSwRba6HvGKtoA1wMbDBebmYsVSdtgHgGjjr8RPffn1OiuK3/RLbQ7ITivhGLT4cpFP/O7PplCO8cj4nxv1nFMbUH0WooqG7Hi1rhoJWTeNkp2whyq9vODFeylk6zX+rU9SBYyg8I3JkAEMzVn1KYYqfs3T/S/cl4bojZv5i18L+erx97u62rntcdcLYvIME20tLNDyvb8x/xQSTjh+Uo7Nwh/oCWElKfb2IPQtBdDsJ9Wt9H+Z3e8mQRN5jcxOro/LtlJHfKt/fTJQtiNfQmhqY2hDeauPa0qtBQ== danigm@sarah');

